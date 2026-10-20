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

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
