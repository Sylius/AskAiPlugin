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

namespace Sylius\AiAssistantPlugin\DependencyInjection;

use Sylius\AiAssistantPlugin\Entity\AgentAction;
use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\Message;
use Sylius\AiAssistantPlugin\Entity\MessageInterface;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public const DEFAULT_SYSTEM_PROMPT = __DIR__ . '/../../config/prompts/system_prompt.txt';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sylius_ai_assistant');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->integerNode('poll_interval')
                    ->defaultValue(2000)
                    ->info('How often (ms) the chat panel polls for new messages while a turn is running.')
                ->end()
                ->arrayNode('tools')
                    ->addDefaultsIfNotSet()
                    ->info('Which Admin MCP tools the agent gets: by name prefix, minus the excluded names.')
                    ->children()
                        ->arrayNode('read_only_prefixes')
                            ->info('Tools whose names start with one of these prefixes are exposed to the agent and run directly (reads).')
                            ->scalarPrototype()->end()
                            ->defaultValue(['list_', 'get_', 'search_', 'count_', 'open_'])
                        ->end()
                        ->arrayNode('write_prefixes')
                            ->info('Tools whose names start with one of these prefixes are exposed, but every write they attempt becomes a proposed action awaiting approval. delete_/refund_ are deliberately absent.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['create_', 'update_', 'add_', 'remove_', 'set_', 'archive_', 'restore_', 'resend_', 'cancel_', 'ship_', 'complete_', 'accept_', 'reject_', 'generate_'])
                        ->end()
                        ->arrayNode('exclude')
                            ->info('Tools never exposed to the agent, by exact name, whatever the prefixes say (e.g. update_channel).')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $resources = $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
        ;

        $this->addResource($resources, 'conversation', Conversation::class, ConversationInterface::class);
        $this->addResource($resources, 'message', Message::class, MessageInterface::class);
        $this->addResource($resources, 'agent_action', AgentAction::class, AgentActionInterface::class);

        $resources->end()->end()->end();
    }

    /**
     * @param class-string $model
     * @param class-string $interface
     */
    private function addResource(NodeBuilder $resources, string $name, string $model, string $interface): void
    {
        $resources
            ->arrayNode($name)
                ->addDefaultsIfNotSet()
                ->children()
                    ->variableNode('options')->end()
                    ->arrayNode('classes')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('model')->defaultValue($model)->cannotBeEmpty()->end()
                            ->scalarNode('interface')->defaultValue($interface)->cannotBeEmpty()->end()
                            ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                            ->scalarNode('repository')->cannotBeEmpty()->end()
                            ->scalarNode('factory')->defaultValue(Factory::class)->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
