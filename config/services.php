<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Sylius\AskAiPlugin\Agent\AdminPagesInputProcessor;
use Sylius\AskAiPlugin\Agent\AgentActionExecutor;
use Sylius\AskAiPlugin\Agent\AgentActionExecutorInterface;
use Sylius\AskAiPlugin\Agent\ChatTurnContext;
use Sylius\AskAiPlugin\Agent\ChatTurnContextInterface;
use Sylius\AskAiPlugin\Agent\CurrentDateInputProcessor;
use Sylius\AskAiPlugin\Agent\Platform\PlatformCallLogger;
use Sylius\AskAiPlugin\Agent\StoreInstructionsInputProcessor;
use Sylius\AskAiPlugin\Agent\Toolbox\ToolCallListener;
use Sylius\AskAiPlugin\Api\ProposingApiClient;
use Sylius\AskAiPlugin\Chat\ConversationChat;
use Sylius\AskAiPlugin\Chat\ConversationChatInterface;
use Sylius\AskAiPlugin\Chat\ConversationMessageStore;
use Sylius\AskAiPlugin\Chat\ConversationTimeline;
use Sylius\AskAiPlugin\Chat\ConversationTimelineInterface;
use Sylius\AskAiPlugin\Controller\Admin\ResumeConversationAction;
use Sylius\AskAiPlugin\DependencyInjection\Compiler\CollectToolsPass;
use Sylius\AskAiPlugin\Factory\AgentActionFactory;
use Sylius\AskAiPlugin\Factory\AgentActionFactoryInterface;
use Sylius\AskAiPlugin\Factory\MessageFactory;
use Sylius\AskAiPlugin\Factory\MessageFactoryInterface;
use Sylius\AskAiPlugin\Mcp\CurrentToolCall;
use Sylius\AskAiPlugin\Mcp\CurrentToolCallInterface;
use Sylius\AskAiPlugin\Menu\AdminMenuListener;
use Sylius\AskAiPlugin\MessageHandler\ExecuteAgentActionHandler;
use Sylius\AskAiPlugin\MessageHandler\RunAgentTurnHandler;
use Sylius\AskAiPlugin\Navigation\AdminUrlResolver;
use Sylius\AskAiPlugin\Navigation\AdminUrlResolverInterface;
use Sylius\AskAiPlugin\Provider\AdminUserTokenProvider;
use Sylius\AskAiPlugin\Provider\AdminUserTokenProviderInterface;
use Sylius\AskAiPlugin\Tool\OpenAdminPage;
use Sylius\AskAiPlugin\Twig\Component\ChatPanelComponent;
use Sylius\AskAiPlugin\Twig\Extension\MarkdownExtension;
use Sylius\AskAiPlugin\Twig\Extension\TimelineExtension;
use Sylius\AskAiPlugin\Twig\Extension\ValueExtension;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_ask_ai.custom_factory.message', MessageFactory::class)
        ->decorate('sylius_ask_ai.factory.message')
        ->args([service('.inner')])
    ;
    $services->alias(MessageFactoryInterface::class, 'sylius_ask_ai.factory.message');
    $services->set('sylius_ask_ai.custom_factory.agent_action', AgentActionFactory::class)
        ->decorate('sylius_ask_ai.factory.agent_action')
        ->args([service('.inner')])
    ;
    $services->alias(AgentActionFactoryInterface::class, 'sylius_ask_ai.factory.agent_action');

    $services->set('sylius_ask_ai.controller.admin.resume_conversation', ResumeConversationAction::class)
        ->args([
            service('sylius_ask_ai.repository.conversation'),
            service('security.helper'),
            service('security.csrf.token_manager'),
            service('router'),
            param('sylius_ask_ai.access.role'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('sylius_ask_ai.twig.component.chat_panel', ChatPanelComponent::class)
        ->args([
            service('sylius_ask_ai.repository.conversation'),
            service('sylius_ask_ai.factory.conversation'),
            service('sylius_ask_ai.factory.message'),
            service('sylius_ask_ai.repository.agent_action'),
            service('doctrine.orm.entity_manager'),
            service('sylius_ask_ai.command_bus'),
            service('security.helper'),
            service('request_stack'),
            service('sylius_ask_ai.chat.timeline'),
            param('sylius_ask_ai.poll_interval'),
            param('sylius_ask_ai.suggestions'),
            param('sylius_ask_ai.access.role'),
        ])
        ->call('setLiveResponder', [service('ux.live_component.live_responder')])
        ->tag('sylius.live_component.admin', [
            'key' => 'sylius_ask_ai:panel',
            'template' => '@SyliusAskAiPlugin/component/chat_panel.html.twig',
        ])
    ;

    $services->set('sylius_ask_ai.twig.extension.markdown', MarkdownExtension::class)
        ->tag('twig.extension')
    ;
    $services->set('sylius_ask_ai.twig.extension.value', ValueExtension::class)
        ->tag('twig.extension')
    ;
    $services->set('sylius_ask_ai.twig.extension.timeline', TimelineExtension::class)
        ->args([service('sylius_ask_ai.chat.timeline')])
        ->tag('twig.extension')
    ;

    $services->set('sylius_ask_ai.chat.timeline', ConversationTimeline::class)
        ->args([service('sylius_ask_ai.repository.agent_action')])
    ;
    $services->alias(ConversationTimelineInterface::class, 'sylius_ask_ai.chat.timeline');

    $services->set('sylius_ask_ai.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;

    $services->set('sylius_ask_ai.agent.context', ChatTurnContext::class);
    $services->alias(ChatTurnContextInterface::class, 'sylius_ask_ai.agent.context');

    $services->alias('sylius_ask_ai.agent', CollectToolsPass::AGENT_SERVICE);
    $services->set('sylius_ask_ai.agent.input_processor.admin_pages', AdminPagesInputProcessor::class)
        ->args([service('sylius_ask_ai.navigation.admin_url_resolver')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;
    $services->set('sylius_ask_ai.agent.input_processor.store_instructions', StoreInstructionsInputProcessor::class)
        ->args([param('sylius_ask_ai.prompt.additional_instructions')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;
    $services->set('sylius_ask_ai.agent.input_processor.current_date', CurrentDateInputProcessor::class)
        ->args([service('clock'), param('sylius_ask_ai.prompt.time_zone')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;

    $services->set('sylius_ask_ai.agent.tool_call_listener', ToolCallListener::class)
        ->args([service('sylius_ask_ai.mcp.current_tool_call')])
        ->tag('kernel.event_listener', ['event' => ToolCallRequested::class, 'method' => 'onToolCallRequested'])
    ;

    $services->set('sylius_ask_ai.agent.platform_call_logger', PlatformCallLogger::class)
        ->args([service('logger')])
        ->tag('monolog.logger', ['channel' => 'sylius_ask_ai'])
        ->tag('kernel.event_listener', ['event' => InvocationEvent::class, 'method' => 'onInvocation'])
        ->tag('kernel.event_listener', ['event' => ResultConvertedEvent::class, 'method' => 'onResultConverted'])
    ;

    $services->set('sylius_ask_ai.chat.message_store', ConversationMessageStore::class)
        ->args([
            service('sylius_ask_ai.repository.conversation'),
            service('sylius_ask_ai.agent.context'),
            service('sylius_ask_ai.factory.message'),
            service('doctrine.orm.entity_manager'),
        ])
    ;

    $services->set('sylius_ask_ai.chat', ConversationChat::class)
        ->args([
            service('sylius_ask_ai.agent'),
            service('sylius_ask_ai.agent.context'),
        ])
    ;
    $services->alias(ConversationChatInterface::class, 'sylius_ask_ai.chat');

    $services->set('sylius_ask_ai.agent.action_executor', AgentActionExecutor::class)
        ->args([
            service('sylius_admin_mcp_server.client.api'),
            service('sylius_ask_ai.provider.admin_user_token'),
        ])
    ;
    $services->alias(AgentActionExecutorInterface::class, 'sylius_ask_ai.agent.action_executor');

    $services->set('sylius_ask_ai.message_handler.run_agent_turn', RunAgentTurnHandler::class)
        ->args([
            service('sylius_ask_ai.repository.conversation'),
            service('sylius_ask_ai.factory.message'),
            service('doctrine'),
            service(ConversationChatInterface::class),
            service('logger'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius_ask_ai.command_bus'])
        ->tag('monolog.logger', ['channel' => 'sylius_ask_ai'])
    ;

    $services->set('sylius_ask_ai.message_handler.execute_agent_action', ExecuteAgentActionHandler::class)
        ->args([
            service('sylius_ask_ai.repository.agent_action'),
            service('sylius_ask_ai.factory.message'),
            service('sylius_ask_ai.agent.action_executor'),
            service('doctrine.orm.entity_manager'),
            service('sylius_ask_ai.command_bus'),
            service('sylius_ask_ai.navigation.admin_url_resolver'),
            service('logger'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius_ask_ai.command_bus'])
        ->tag('monolog.logger', ['channel' => 'sylius_ask_ai'])
    ;

    $services->set('sylius_ask_ai.navigation.admin_url_resolver', AdminUrlResolver::class)
        ->args([
            abstract_arg('the pages, from sylius_ask_ai.navigation.pages'),
            abstract_arg('the locator of the repositories of the pages'),
            service('property_accessor'),
            service('router'),
        ])
    ;
    $services->alias(AdminUrlResolverInterface::class, 'sylius_ask_ai.navigation.admin_url_resolver');

    $services->set('sylius_ask_ai.tool.open_admin_page', OpenAdminPage::class)
        ->args([
            service('sylius_ask_ai.navigation.admin_url_resolver'),
            service('sylius_ask_ai.agent.context'),
            service('sylius_ask_ai.repository.conversation'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag(CollectToolsPass::TOOL_TAG)
    ;

    $services->set('sylius_ask_ai.provider.admin_user_token', AdminUserTokenProvider::class)
        ->decorate('sylius_admin_mcp_server.provider.token')
        ->args([
            service('.inner'),
            service('lexik_jwt_authentication.jwt_manager'),
            service('sylius.repository.admin_user'),
            service('sylius_ask_ai.agent.context'),
        ])
    ;
    $services->alias(AdminUserTokenProviderInterface::class, 'sylius_ask_ai.provider.admin_user_token');

    $services->set('sylius_ask_ai.mcp.current_tool_call', CurrentToolCall::class);
    $services->alias(CurrentToolCallInterface::class, 'sylius_ask_ai.mcp.current_tool_call');

    $services->set('sylius_ask_ai.api.proposing_client', ProposingApiClient::class)
        ->decorate('sylius_admin_mcp_server.client.api')
        ->args([
            service('.inner'),
            service('sylius_ask_ai.agent.context'),
            service('sylius_ask_ai.mcp.current_tool_call'),
            service('sylius_ask_ai.repository.conversation'),
            service('sylius_ask_ai.factory.agent_action'),
            service('doctrine.orm.entity_manager'),
        ])
    ;
};
