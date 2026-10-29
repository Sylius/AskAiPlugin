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

use Sylius\AiAssistantPlugin\Agent\AdminPagesInputProcessor;
use Sylius\AiAssistantPlugin\Agent\AgentActionExecutor;
use Sylius\AiAssistantPlugin\Agent\AgentActionExecutorInterface;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContext;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\AiAssistantPlugin\Agent\CurrentDateInputProcessor;
use Sylius\AiAssistantPlugin\Agent\Platform\PlatformCallLogger;
use Sylius\AiAssistantPlugin\Agent\StoreInstructionsInputProcessor;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallListener;
use Sylius\AiAssistantPlugin\Api\ProposingApiClient;
use Sylius\AiAssistantPlugin\Chat\ConversationChat;
use Sylius\AiAssistantPlugin\Chat\ConversationChatInterface;
use Sylius\AiAssistantPlugin\Chat\ConversationMessageStore;
use Sylius\AiAssistantPlugin\Chat\ConversationTimeline;
use Sylius\AiAssistantPlugin\Chat\ConversationTimelineInterface;
use Sylius\AiAssistantPlugin\Controller\Admin\ResumeConversationAction;
use Sylius\AiAssistantPlugin\DependencyInjection\Compiler\CollectToolsPass;
use Sylius\AiAssistantPlugin\Factory\AgentActionFactory;
use Sylius\AiAssistantPlugin\Factory\AgentActionFactoryInterface;
use Sylius\AiAssistantPlugin\Factory\MessageFactory;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\AiAssistantPlugin\Mcp\CurrentToolCall;
use Sylius\AiAssistantPlugin\Mcp\CurrentToolCallInterface;
use Sylius\AiAssistantPlugin\Menu\AdminMenuListener;
use Sylius\AiAssistantPlugin\MessageHandler\ExecuteAgentActionHandler;
use Sylius\AiAssistantPlugin\MessageHandler\RunAgentTurnHandler;
use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolver;
use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolverInterface;
use Sylius\AiAssistantPlugin\Provider\AdminUserTokenProvider;
use Sylius\AiAssistantPlugin\Provider\AdminUserTokenProviderInterface;
use Sylius\AiAssistantPlugin\Repository\ConversationRepositoryInterface;
use Sylius\AiAssistantPlugin\Tool\OpenAdminPage;
use Sylius\AiAssistantPlugin\Twig\Component\ChatPanelComponent;
use Sylius\AiAssistantPlugin\Twig\Extension\MarkdownExtension;
use Sylius\AiAssistantPlugin\Twig\Extension\TimelineExtension;
use Sylius\AiAssistantPlugin\Twig\Extension\ValueExtension;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('sylius_ai_assistant.custom_factory.message', MessageFactory::class)
        ->decorate('sylius_ai_assistant.factory.message')
        ->args([service('.inner')])
    ;
    $services->alias(MessageFactoryInterface::class, 'sylius_ai_assistant.factory.message');
    $services->set('sylius_ai_assistant.custom_factory.agent_action', AgentActionFactory::class)
        ->decorate('sylius_ai_assistant.factory.agent_action')
        ->args([service('.inner')])
    ;
    $services->alias(AgentActionFactoryInterface::class, 'sylius_ai_assistant.factory.agent_action');

    $services->alias(ConversationRepositoryInterface::class, 'sylius_ai_assistant.repository.conversation');

    $services->set('sylius_ai_assistant.controller.admin.resume_conversation', ResumeConversationAction::class)
        ->args([
            service('sylius_ai_assistant.repository.conversation'),
            service('doctrine.orm.entity_manager'),
            service('security.helper'),
            service('security.csrf.token_manager'),
            service('router'),
            param('sylius_ai_assistant.access.role'),
        ])
        ->tag('controller.service_arguments')
    ;

    $services->set('sylius_ai_assistant.twig.component.chat_panel', ChatPanelComponent::class)
        ->args([
            service('sylius_ai_assistant.repository.conversation'),
            service('sylius_ai_assistant.factory.conversation'),
            service('sylius_ai_assistant.factory.message'),
            service('sylius_ai_assistant.repository.agent_action'),
            service('doctrine.orm.entity_manager'),
            service('sylius_ai_assistant.command_bus'),
            service('security.helper'),
            service('request_stack'),
            service('sylius_ai_assistant.chat.timeline'),
            param('sylius_ai_assistant.poll_interval'),
            param('sylius_ai_assistant.suggestions'),
            param('sylius_ai_assistant.access.role'),
        ])
        ->call('setLiveResponder', [service('ux.live_component.live_responder')])
        ->tag('sylius.live_component.admin', [
            'key' => 'sylius_ai_assistant:panel',
            'template' => '@SyliusAiAssistantPlugin/component/chat_panel.html.twig',
        ])
    ;

    $services->set('sylius_ai_assistant.twig.extension.markdown', MarkdownExtension::class)
        ->tag('twig.extension')
    ;
    $services->set('sylius_ai_assistant.twig.extension.value', ValueExtension::class)
        ->tag('twig.extension')
    ;
    $services->set('sylius_ai_assistant.twig.extension.timeline', TimelineExtension::class)
        ->args([service('sylius_ai_assistant.chat.timeline')])
        ->tag('twig.extension')
    ;

    $services->set('sylius_ai_assistant.chat.timeline', ConversationTimeline::class)
        ->args([service('sylius_ai_assistant.repository.agent_action')])
    ;
    $services->alias(ConversationTimelineInterface::class, 'sylius_ai_assistant.chat.timeline');

    $services->set('sylius_ai_assistant.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;

    $services->set('sylius_ai_assistant.agent.context', ChatTurnContext::class);
    $services->alias(ChatTurnContextInterface::class, 'sylius_ai_assistant.agent.context');

    $services->alias('sylius_ai_assistant.agent', CollectToolsPass::AGENT_SERVICE);
    $services->set('sylius_ai_assistant.agent.input_processor.admin_pages', AdminPagesInputProcessor::class)
        ->args([service('sylius_ai_assistant.navigation.admin_url_resolver')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;
    $services->set('sylius_ai_assistant.agent.input_processor.store_instructions', StoreInstructionsInputProcessor::class)
        ->args([param('sylius_ai_assistant.prompt.additional_instructions')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;
    $services->set('sylius_ai_assistant.agent.input_processor.current_date', CurrentDateInputProcessor::class)
        ->args([service('clock'), param('sylius_ai_assistant.prompt.time_zone')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;

    $services->set('sylius_ai_assistant.agent.tool_call_listener', ToolCallListener::class)
        ->args([service('sylius_ai_assistant.mcp.current_tool_call')])
        ->tag('kernel.event_listener', ['event' => ToolCallRequested::class, 'method' => 'onToolCallRequested'])
    ;

    $services->set('sylius_ai_assistant.agent.platform_call_logger', PlatformCallLogger::class)
        ->args([service('logger')])
        ->tag('monolog.logger', ['channel' => 'sylius_ai_assistant'])
        ->tag('kernel.event_listener', ['event' => InvocationEvent::class, 'method' => 'onInvocation'])
        ->tag('kernel.event_listener', ['event' => ResultConvertedEvent::class, 'method' => 'onResultConverted'])
    ;

    $services->set('sylius_ai_assistant.chat.message_store', ConversationMessageStore::class)
        ->args([
            service('sylius_ai_assistant.repository.conversation'),
            service('sylius_ai_assistant.agent.context'),
            service('sylius_ai_assistant.factory.message'),
            service('doctrine.orm.entity_manager'),
        ])
    ;

    $services->set('sylius_ai_assistant.chat', ConversationChat::class)
        ->args([
            service('sylius_ai_assistant.agent'),
            service('sylius_ai_assistant.agent.context'),
        ])
    ;
    $services->alias(ConversationChatInterface::class, 'sylius_ai_assistant.chat');

    $services->set('sylius_ai_assistant.agent.action_executor', AgentActionExecutor::class)
        ->args([
            service('sylius_admin_mcp_server.client.api'),
            service('sylius_ai_assistant.provider.admin_user_token'),
        ])
    ;
    $services->alias(AgentActionExecutorInterface::class, 'sylius_ai_assistant.agent.action_executor');

    $services->set('sylius_ai_assistant.message_handler.run_agent_turn', RunAgentTurnHandler::class)
        ->args([
            service('sylius_ai_assistant.repository.conversation'),
            service('sylius_ai_assistant.factory.message'),
            service('doctrine'),
            service(ConversationChatInterface::class),
            service('logger'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius_ai_assistant.command_bus'])
        ->tag('monolog.logger', ['channel' => 'sylius_ai_assistant'])
    ;

    $services->set('sylius_ai_assistant.message_handler.execute_agent_action', ExecuteAgentActionHandler::class)
        ->args([
            service('sylius_ai_assistant.repository.agent_action'),
            service('sylius_ai_assistant.factory.message'),
            service('sylius_ai_assistant.agent.action_executor'),
            service('doctrine.orm.entity_manager'),
            service('sylius_ai_assistant.command_bus'),
            service('sylius_ai_assistant.navigation.admin_url_resolver'),
            service('logger'),
        ])
        ->tag('messenger.message_handler', ['bus' => 'sylius_ai_assistant.command_bus'])
        ->tag('monolog.logger', ['channel' => 'sylius_ai_assistant'])
    ;

    $services->set('sylius_ai_assistant.navigation.admin_url_resolver', AdminUrlResolver::class)
        ->args([
            abstract_arg('the pages, from sylius_ai_assistant.navigation.pages'),
            abstract_arg('the locator of the repositories of the pages'),
            service('property_accessor'),
            service('router'),
        ])
    ;
    $services->alias(AdminUrlResolverInterface::class, 'sylius_ai_assistant.navigation.admin_url_resolver');

    $services->set('sylius_ai_assistant.tool.open_admin_page', OpenAdminPage::class)
        ->args([
            service('sylius_ai_assistant.navigation.admin_url_resolver'),
            service('sylius_ai_assistant.agent.context'),
            service('sylius_ai_assistant.repository.conversation'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag(CollectToolsPass::TOOL_TAG)
    ;

    $services->set('sylius_ai_assistant.provider.admin_user_token', AdminUserTokenProvider::class)
        ->decorate('sylius_admin_mcp_server.provider.token')
        ->args([
            service('.inner'),
            service('lexik_jwt_authentication.jwt_manager'),
            service('sylius.repository.admin_user'),
            service('sylius_ai_assistant.agent.context'),
        ])
    ;
    $services->alias(AdminUserTokenProviderInterface::class, 'sylius_ai_assistant.provider.admin_user_token');

    $services->set('sylius_ai_assistant.mcp.current_tool_call', CurrentToolCall::class);
    $services->alias(CurrentToolCallInterface::class, 'sylius_ai_assistant.mcp.current_tool_call');

    $services->set('sylius_ai_assistant.api.proposing_client', ProposingApiClient::class)
        ->decorate('sylius_admin_mcp_server.client.api')
        ->args([
            service('.inner'),
            service('sylius_ai_assistant.agent.context'),
            service('sylius_ai_assistant.mcp.current_tool_call'),
            service('sylius_ai_assistant.repository.conversation'),
            service('sylius_ai_assistant.factory.agent_action'),
            service('doctrine.orm.entity_manager'),
        ])
    ;
};
