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

namespace Tests\Sylius\AiAssistantPlugin\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Sylius\AiAssistantPlugin\DependencyInjection\Configuration;
use Sylius\AiAssistantPlugin\Entity\AgentAction;
use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\Message;
use Sylius\AiAssistantPlugin\Entity\MessageInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testTheResourcesAreRegisteredWithTheirDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['driver']);
        self::assertSame(['conversation', 'message', 'agent_action'], array_keys($config['resources']));

        foreach ([
            'conversation' => [Conversation::class, ConversationInterface::class],
            'message' => [Message::class, MessageInterface::class],
            'agent_action' => [AgentAction::class, AgentActionInterface::class],
        ] as $resource => [$model, $interface]) {
            $classes = $config['resources'][$resource]['classes'];
            self::assertSame($model, $classes['model']);
            self::assertSame($interface, $classes['interface']);
            self::assertSame(ResourceController::class, $classes['controller']);
            self::assertSame(Factory::class, $classes['factory']);
            self::assertArrayNotHasKey('repository', $classes, 'The resource bundle picks its default repository.');
        }
    }

    public function testAModelClassCanBeReplaced(): void
    {
        $config = $this->process([
            'resources' => ['conversation' => ['classes' => ['model' => 'App\Entity\Conversation']]],
        ]);

        self::assertSame('App\Entity\Conversation', $config['resources']['conversation']['classes']['model']);
        self::assertSame(ConversationInterface::class, $config['resources']['conversation']['classes']['interface']);
        self::assertSame(Message::class, $config['resources']['message']['classes']['model'], 'Other resources keep their defaults.');
    }

    public function testToolPrefixesLiveUnderTools(): void
    {
        $config = $this->process([]);

        self::assertSame(['list_', 'get_', 'search_', 'count_', 'open_'], $config['tools']['read_only_prefixes']);
        self::assertContains('update_', $config['tools']['write_prefixes']);
        self::assertNotContains('delete_', $config['tools']['write_prefixes']);

        $config = $this->process(['tools' => ['write_prefixes' => []]]);
        self::assertSame([], $config['tools']['write_prefixes'], 'Writes can be switched off entirely.');
        self::assertSame(['list_', 'get_', 'search_', 'count_', 'open_'], $config['tools']['read_only_prefixes']);
    }

    public function testSingleToolsCanBeExcludedByName(): void
    {
        self::assertSame([], $this->process([])['tools']['exclude']);

        $config = $this->process(['tools' => ['exclude' => ['update_channel', 'remove_product']]]);
        self::assertSame(['update_channel', 'remove_product'], $config['tools']['exclude']);
        self::assertContains('update_', $config['tools']['write_prefixes'], 'Excluding a tool leaves the prefixes alone.');
    }

    public function testTheStoreAddsInstructionsToThePrompt(): void
    {
        self::assertNull($this->process([])['prompt']['additional_instructions']);
        self::assertSame("Answer in Polish.\n", $this->process(['prompt' => ['additional_instructions' => "Answer in Polish.\n"]])['prompt']['additional_instructions']);
    }

    public function testTheStoreReplacesTheSuggestedQuestions(): void
    {
        self::assertSame([
            'sylius_ai_assistant.ui.suggestion.what_are_the_latest_orders',
            'sylius_ai_assistant.ui.suggestion.which_orders_are_waiting_to_be_shipped',
            'sylius_ai_assistant.ui.suggestion.create_a_customer_group_called_vip',
        ], $this->process([])['suggestions']);

        $config = $this->process(['suggestions' => ['app.ai_assistant.suggestion.unpaid_orders', 'Which coupons expire this month?']]);
        self::assertSame(['app.ai_assistant.suggestion.unpaid_orders', 'Which coupons expire this month?'], $config['suggestions'], 'Keys and plain text mix; the list replaces the default one.');

        self::assertSame([], $this->process(['suggestions' => []])['suggestions'], 'No suggestions at all is a choice.');

        $config = $this->process(['suggestions' => ['app.ai_assistant.suggestion.unpaid_orders']], ['suggestions' => []]);
        self::assertSame([], $config['suggestions'], 'A later file (when@test, a dev override) replaces the list instead of appending to it.');
    }

    public function testThePanelIsForApiAccessByDefault(): void
    {
        self::assertSame('ROLE_API_ACCESS', $this->process([])['access']['role']);
        self::assertSame('ROLE_AI_ASSISTANT', $this->process(['access' => ['role' => 'ROLE_AI_ASSISTANT']])['access']['role']);
    }

    public function testTheNavigationPagesMergeAcrossConfigsAndSwitchOffWithFalse(): void
    {
        self::assertSame([], $this->process([])['navigation']['pages'], 'The defaults come from config/config.yaml, not from the tree.');

        $plugin = ['navigation' => ['pages' => [
            'products' => ['repository' => 'sylius.repository.product', 'field' => 'code', 'edit' => 'sylius_admin_product_update'],
            'orders' => ['repository' => 'sylius.repository.order', 'field' => 'tokenValue', 'edit' => 'sylius_admin_order_update', 'index' => 'sylius_admin_order_index', 'search' => 'number'],
        ]]];
        $application = ['navigation' => ['pages' => [
            'orders' => false,
            'tickets' => ['repository' => 'app.repository.ticket', 'field' => 'id', 'edit' => 'app_admin_ticket_update', 'parent' => ['param' => 'customerId', 'path' => 'customer.id']],
        ]]];

        $pages = (new Processor())->processConfiguration(new Configuration(), [$plugin, $application])['navigation']['pages'];

        self::assertSame(['products', 'tickets'], array_keys($pages));
        self::assertSame(['param' => 'customerId', 'path' => 'customer.id'], $pages['tickets']['parent']);
        self::assertArrayNotHasKey('show', $pages['tickets'], 'Optional routes are absent, not null.');
    }

    public function testANavigationPageNeedsItsRepositoryFieldAndEditRoute(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageMatches('/"edit".*must be configured/');

        $this->process(['navigation' => ['pages' => ['tickets' => ['repository' => 'app.repository.ticket', 'field' => 'id']]]]);
    }

    /**
     * @param array<string, mixed> ...$configs
     *
     * @return array<string, mixed>
     */
    private function process(array ...$configs): array
    {
        return (new Processor())->processConfiguration(new Configuration(), $configs);
    }
}
