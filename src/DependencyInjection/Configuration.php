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

namespace Sylius\AskAiPlugin\DependencyInjection;

use Sylius\AskAiPlugin\Entity\AgentAction;
use Sylius\AskAiPlugin\Entity\AgentActionInterface;
use Sylius\AskAiPlugin\Entity\Conversation;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\Message;
use Sylius\AskAiPlugin\Entity\MessageInterface;
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
        $treeBuilder = new TreeBuilder('sylius_ask_ai');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('driver')->defaultValue(SyliusResourceBundle::DRIVER_DOCTRINE_ORM)->end()
                ->arrayNode('access')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('role')
                            ->cannotBeEmpty()
                            ->defaultValue('ROLE_API_ACCESS')
                            ->info('The role an administrator needs to see and use the chat panel. The agent calls the Admin API as them, which takes ROLE_API_ACCESS; a role of your own must imply it.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('prompt')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('additional_instructions')
                            ->defaultNull()
                            ->info('Rules of the store appended to the system prompt ("always answer in Polish", "our SKUs look like FW-1234"), without copying the prompt file.')
                        ->end()
                        ->scalarNode('time_zone')
                            ->defaultNull()
                            ->info('The time zone "today" is counted in (Europe/Warsaw); by default the one of the PHP process running the worker.')
                            ->validate()
                                ->ifTrue(static fn (mixed $zone): bool => null !== $zone && !in_array($zone, \DateTimeZone::listIdentifiers(), true))
                                ->thenInvalid('Unknown time zone %s.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->integerNode('poll_interval')
                    ->defaultValue(2000)
                    ->info('How often (ms) the chat panel polls for new messages while a turn is running.')
                ->end()
                ->integerNode('max_output_tokens')
                    ->defaultValue(8192)
                    ->min(1)
                    ->info('The output token budget of one answer of the default model; a proposed write carries the whole resource.')
                ->end()
                ->arrayNode('suggestions')
                    ->performNoDeepMerging()
                    ->info('The questions offered on an empty conversation, in order; each is a translation key or plain text. The list replaces the default one.')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([
                        'sylius_ask_ai.ui.suggestion.what_are_the_latest_orders',
                        'sylius_ask_ai.ui.suggestion.which_orders_are_waiting_to_be_shipped',
                        'sylius_ask_ai.ui.suggestion.create_a_customer_group_called_vip',
                    ])
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

        $this->addNavigationSection($rootNode);
        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addNavigationSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('navigation')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('pages')
                            ->info('The admin pages the agent may open, keyed by the Admin API collection segment (products, orders, …); one entry switches off with `false`.')
                            ->useAttributeAsKey('resource')
                            ->normalizeKeys(false)
                            ->arrayPrototype()
                                ->canBeUnset()
                                ->children()
                                    ->scalarNode('repository')->isRequired()->cannotBeEmpty()->info('Service id of the repository the identifier is looked up in.')->end()
                                    ->scalarNode('field')->isRequired()->cannotBeEmpty()->info('The entity field the Admin API identifies the resource by (code, tokenValue, id).')->end()
                                    ->scalarNode('show')->cannotBeEmpty()->info('Route of the show page; without it "show" opens the edit page.')->end()
                                    ->scalarNode('edit')->isRequired()->cannotBeEmpty()->info('Route of the edit page.')->end()
                                    ->scalarNode('index')->cannotBeEmpty()->info('Route of the list page; without it the resource has no list page.')->end()
                                    ->scalarNode('search')->cannotBeEmpty()->info('The grid filter a search term goes to (criteria[<search>][value]); without it the term is ignored.')->end()
                                    ->arrayNode('parent')
                                        ->info('For a nested resource: the route parameter of the parent and the property path it is read from.')
                                        ->children()
                                            ->scalarNode('param')->isRequired()->cannotBeEmpty()->end()
                                            ->scalarNode('path')->isRequired()->cannotBeEmpty()->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
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
