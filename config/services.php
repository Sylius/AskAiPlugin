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
use Sylius\AiAssistantPlugin\Agent\Platform\PlatformCallLogger;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallListener;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallTrace;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallTraceInterface;
use Sylius\AiAssistantPlugin\Api\ProposingApiClient;
use Sylius\AiAssistantPlugin\Chat\ConversationChat;
use Sylius\AiAssistantPlugin\Chat\ConversationChatInterface;
use Sylius\AiAssistantPlugin\Chat\ConversationTimeline;
use Sylius\AiAssistantPlugin\Chat\ConversationTimelineInterface;
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
use Sylius\AiAssistantPlugin\Tool\OpenAdminPage;
use Sylius\AiAssistantPlugin\Twig\Component\ChatPanelComponent;
use Sylius\AiAssistantPlugin\Twig\Extension\MarkdownExtension;
use Sylius\AiAssistantPlugin\Twig\Extension\TimelineExtension;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // --- Resources: the factories of the entities that are never created empty ---------------
    // They decorate the resource bundle's factories, so a replaced model class is still what gets created.
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
    $services->set('sylius_ai_assistant.twig.extension.timeline', TimelineExtension::class)
        ->args([service('sylius_ai_assistant.chat.timeline')])
        ->tag('twig.extension')
    ;

    $services->set('sylius_ai_assistant.chat.timeline', ConversationTimeline::class)
        ->args([service('sylius_ai_assistant.repository.agent_action')])
    ;
    $services->alias(ConversationTimelineInterface::class, 'sylius_ai_assistant.chat.timeline');

    // --- Admin log: the "AI assistant" section of the admin menu ------------------------------
    $services->set('sylius_ai_assistant.listener.admin_menu', AdminMenuListener::class)
        ->tag('kernel.event_listener', ['event' => 'sylius.menu.admin.main'])
    ;

    $services->set('sylius_ai_assistant.agent.context', ChatTurnContext::class);
    $services->alias(ChatTurnContextInterface::class, 'sylius_ai_assistant.agent.context');

    // --- Symfony AI: the agent, its toolbox and the chat ---------------------------------

    $services->set('sylius_ai_assistant.agent.tool_call_trace', ToolCallTrace::class);
    $services->alias(ToolCallTraceInterface::class, 'sylius_ai_assistant.agent.tool_call_trace');

    // The agent is "ai.agent.sylius_ai_assistant" from symfony/ai-bundle (prepended by the extension); these
    // processors are tagged for it. The system prompt is the bundle's own (prompt.file), {pages} is its template variable.
    $services->alias('sylius_ai_assistant.agent', CollectToolsPass::AGENT_SERVICE);
    $services->set('sylius_ai_assistant.agent.input_processor.admin_pages', AdminPagesInputProcessor::class)
        ->args([service('sylius_ai_assistant.navigation.admin_url_resolver')])
        ->tag('ai.agent.input_processor', ['agent' => CollectToolsPass::AGENT_SERVICE])
    ;

    $services->set('sylius_ai_assistant.agent.tool_call_listener', ToolCallListener::class)
        ->args([
            service('sylius_ai_assistant.mcp.current_tool_call'),
            service('sylius_ai_assistant.agent.tool_call_trace'),
        ])
        ->tag('kernel.event_listener', ['event' => ToolCallRequested::class, 'method' => 'onToolCallRequested'])
    ;

    // What goes to the model and what comes back, at debug level.
    $services->set('sylius_ai_assistant.agent.platform_call_logger', PlatformCallLogger::class)
        ->args([service('logger')])
        ->tag('monolog.logger', ['channel' => 'sylius_ai_assistant'])
        ->tag('kernel.event_listener', ['event' => InvocationEvent::class, 'method' => 'onInvocation'])
        ->tag('kernel.event_listener', ['event' => ResultConvertedEvent::class, 'method' => 'onResultConverted'])
    ;

    $services->set('sylius_ai_assistant.chat', ConversationChat::class)
        ->args([
            service('sylius_ai_assistant.agent'),
            service('sylius_ai_assistant.factory.message'),
            service('doctrine.orm.entity_manager'),
            service('sylius_ai_assistant.agent.context'),
            service('sylius_ai_assistant.agent.tool_call_trace'),
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

    // --- Navigation ------------------------------------------------------------------------

    // Whitelist of admin pages the agent may open, keyed by the Admin API collection segment:
    // the resource's show/edit page and its list page (with the grid filter used for search).
    $pages = [
        'products' => ['repository' => 'sylius.repository.product', 'field' => 'code', 'show' => 'sylius_admin_product_show', 'edit' => 'sylius_admin_product_update', 'index' => 'sylius_admin_product_index', 'search' => 'search'],
        'product-variants' => ['repository' => 'sylius.repository.product_variant', 'field' => 'code', 'edit' => 'sylius_admin_product_variant_update', 'parent' => ['param' => 'productId', 'path' => 'product.id']],
        'orders' => ['repository' => 'sylius.repository.order', 'field' => 'tokenValue', 'show' => 'sylius_admin_order_show', 'edit' => 'sylius_admin_order_update', 'index' => 'sylius_admin_order_index', 'search' => 'number'],
        'customers' => ['repository' => 'sylius.repository.customer', 'field' => 'id', 'show' => 'sylius_admin_customer_show', 'edit' => 'sylius_admin_customer_update', 'index' => 'sylius_admin_customer_index', 'search' => 'search'],
        'customer-groups' => ['repository' => 'sylius.repository.customer_group', 'field' => 'code', 'edit' => 'sylius_admin_customer_group_update', 'index' => 'sylius_admin_customer_group_index', 'search' => 'search'],
        'promotions' => ['repository' => 'sylius.repository.promotion', 'field' => 'code', 'edit' => 'sylius_admin_promotion_update', 'index' => 'sylius_admin_promotion_index', 'search' => 'search'],
        'promotion-coupons' => ['repository' => 'sylius.repository.promotion_coupon', 'field' => 'code', 'edit' => 'sylius_admin_promotion_coupon_update', 'parent' => ['param' => 'promotionId', 'path' => 'promotion.id']],
        'catalog-promotions' => ['repository' => 'sylius.repository.catalog_promotion', 'field' => 'code', 'show' => 'sylius_admin_catalog_promotion_show', 'edit' => 'sylius_admin_catalog_promotion_update', 'index' => 'sylius_admin_catalog_promotion_index', 'search' => 'search'],
        'taxons' => ['repository' => 'sylius.repository.taxon', 'field' => 'code', 'edit' => 'sylius_admin_taxon_update', 'index' => 'sylius_admin_taxon_index'],
        'channels' => ['repository' => 'sylius.repository.channel', 'field' => 'code', 'edit' => 'sylius_admin_channel_update', 'index' => 'sylius_admin_channel_index', 'search' => 'search'],
        'shipping-methods' => ['repository' => 'sylius.repository.shipping_method', 'field' => 'code', 'edit' => 'sylius_admin_shipping_method_update', 'index' => 'sylius_admin_shipping_method_index', 'search' => 'search'],
        'payment-methods' => ['repository' => 'sylius.repository.payment_method', 'field' => 'code', 'edit' => 'sylius_admin_payment_method_update', 'index' => 'sylius_admin_payment_method_index', 'search' => 'search'],
        'zones' => ['repository' => 'sylius.repository.zone', 'field' => 'code', 'edit' => 'sylius_admin_zone_update', 'index' => 'sylius_admin_zone_index', 'search' => 'name'],
        'tax-categories' => ['repository' => 'sylius.repository.tax_category', 'field' => 'code', 'edit' => 'sylius_admin_tax_category_update', 'index' => 'sylius_admin_tax_category_index', 'search' => 'search'],
        'tax-rates' => ['repository' => 'sylius.repository.tax_rate', 'field' => 'code', 'edit' => 'sylius_admin_tax_rate_update', 'index' => 'sylius_admin_tax_rate_index'],
        'countries' => ['repository' => 'sylius.repository.country', 'field' => 'code', 'edit' => 'sylius_admin_country_update', 'index' => 'sylius_admin_country_index'],
        'currencies' => ['repository' => 'sylius.repository.currency', 'field' => 'code', 'edit' => 'sylius_admin_currency_update', 'index' => 'sylius_admin_currency_index'],
        'locales' => ['repository' => 'sylius.repository.locale', 'field' => 'code', 'edit' => 'sylius_admin_locale_update', 'index' => 'sylius_admin_locale_index'],
        'product-options' => ['repository' => 'sylius.repository.product_option', 'field' => 'code', 'edit' => 'sylius_admin_product_option_update', 'index' => 'sylius_admin_product_option_index', 'search' => 'search'],
        'product-attributes' => ['repository' => 'sylius.repository.product_attribute', 'field' => 'code', 'edit' => 'sylius_admin_product_attribute_update', 'index' => 'sylius_admin_product_attribute_index', 'search' => 'search'],
        'product-association-types' => ['repository' => 'sylius.repository.product_association_type', 'field' => 'code', 'edit' => 'sylius_admin_product_association_type_update', 'index' => 'sylius_admin_product_association_type_index'],
        'product-reviews' => ['repository' => 'sylius.repository.product_review', 'field' => 'id', 'edit' => 'sylius_admin_product_review_update', 'index' => 'sylius_admin_product_review_index', 'search' => 'title'],
        'exchange-rates' => ['repository' => 'sylius.repository.exchange_rate', 'field' => 'id', 'edit' => 'sylius_admin_exchange_rate_update', 'index' => 'sylius_admin_exchange_rate_index'],
        'administrators' => ['repository' => 'sylius.repository.admin_user', 'field' => 'id', 'edit' => 'sylius_admin_admin_user_update', 'index' => 'sylius_admin_admin_user_index', 'search' => 'search'],
    ];
    $services->set('sylius_ai_assistant.navigation.admin_url_resolver', AdminUrlResolver::class)
        ->args([
            $pages,
            service_locator(array_combine(
                array_column($pages, 'repository'),
                array_map(static fn (string $id) => service($id), array_column($pages, 'repository')),
            )),
            service('property_accessor'),
            service('router'),
        ])
    ;
    $services->alias(AdminUrlResolverInterface::class, 'sylius_ai_assistant.navigation.admin_url_resolver');

    // The assistant's own tool, for the agent only (not served by the MCP server).
    $services->set('sylius_ai_assistant.tool.open_admin_page', OpenAdminPage::class)
        ->args([
            service('sylius_ai_assistant.navigation.admin_url_resolver'),
            service('sylius_ai_assistant.agent.context'),
            service('sylius_ai_assistant.repository.conversation'),
            service('doctrine.orm.entity_manager'),
        ])
        ->tag(CollectToolsPass::TOOL_TAG)
    ;

    // --- Hooks into the Admin MCP plugin -------------------------------------------------

    // Inside a chat turn the Admin API is called as the conversation's administrator; the action
    // executor calls it as the approving administrator.
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

    // Which tool is running, for labelling proposed actions.
    $services->set('sylius_ai_assistant.mcp.current_tool_call', CurrentToolCall::class);
    $services->alias(CurrentToolCallInterface::class, 'sylius_ai_assistant.mcp.current_tool_call');

    // The write gate: inside a chat turn, writes become proposed actions instead of API calls.
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
