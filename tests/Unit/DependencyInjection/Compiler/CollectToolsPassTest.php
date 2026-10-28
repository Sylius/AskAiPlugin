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

namespace Tests\Sylius\AiAssistantPlugin\Unit\DependencyInjection\Compiler;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\TestCase;
use Sylius\AiAssistantPlugin\DependencyInjection\Compiler\CollectToolsPass;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CollectToolsPassTest extends TestCase
{
    public function testTheToolboxGetsTheAdminMcpToolsMatchingThePrefixesExceptTheExcludedOnes(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: ['update_'], exclude: ['update_channel']);

        (new CollectToolsPass())->process($container);

        self::assertSame(['list_channels', 'update_product'], $this->registeredAdminMcpTools($container));
        self::assertSame([ListChannelsTool::class, UpdateProductTool::class], $this->toolbox($container));
    }

    public function testNothingIsExcludedByDefault(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: ['update_'], exclude: []);

        (new CollectToolsPass())->process($container);

        self::assertSame(['list_channels', 'update_channel', 'update_product'], $this->registeredAdminMcpTools($container));
    }

    public function testTheAssistantsOwnToolsJoinWithoutBeingAdminMcpTools(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: [], exclude: []);
        $container->setDefinition('app.tool.own', (new Definition(OwnTool::class))->addTag(CollectToolsPass::TOOL_TAG));

        (new CollectToolsPass())->process($container);

        self::assertSame(['app.tool.own', ListChannelsTool::class], $this->toolbox($container));
        self::assertSame(['list_channels'], $this->registeredAdminMcpTools($container), 'An own tool is described by its #[AsTool], not by the memory factory.');
    }

    public function testTheToolsTheBundleTaggedForEveryAgentAreKept(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: [], exclude: []);
        $container->setDefinition('app.tool.global', (new Definition(OwnTool::class))->addTag('ai.tool'));

        (new CollectToolsPass())->process($container);

        self::assertSame(['app.tool.global', ListChannelsTool::class], $this->toolbox($container));
    }

    public function testTheExplicitListFromAiYamlIsKept(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: [], exclude: []);
        $container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE)->replaceArgument(0, [new Reference('app.tool.listed')]);
        $container->setDefinition('app.tool.listed', new Definition(OwnTool::class));

        (new CollectToolsPass())->process($container);

        self::assertSame(['app.tool.listed', ListChannelsTool::class], $this->toolbox($container));
    }

    public function testATaggedToolListedInAiYamlTooIsAddedOnce(): void
    {
        $container = $this->container(readOnlyPrefixes: [], writePrefixes: [], exclude: []);
        $container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE)->replaceArgument(0, [new Reference('app.tool.own')]);
        $container->setDefinition('app.tool.own', (new Definition(OwnTool::class))->addTag(CollectToolsPass::TOOL_TAG));

        (new CollectToolsPass())->process($container);

        self::assertSame(['app.tool.own'], $this->toolbox($container));
    }

    /**
     * @param list<string> $readOnlyPrefixes
     * @param list<string> $writePrefixes
     * @param list<string> $exclude
     */
    private function container(array $readOnlyPrefixes, array $writePrefixes, array $exclude): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('sylius_ai_assistant.tools.read_only_prefixes', $readOnlyPrefixes);
        $container->setParameter('sylius_ai_assistant.tools.write_prefixes', $writePrefixes);
        $container->setParameter('sylius_ai_assistant.tools.exclude', $exclude);

        $container->setDefinition('ai.toolbox.abstract', (new Definition(\stdClass::class))->setAbstract(true)->setArguments([new TaggedIteratorArgument('ai.tool')]));
        $container->setDefinition(CollectToolsPass::TOOLBOX_SERVICE, new ChildDefinition('ai.toolbox.abstract'));
        $container->setDefinition(CollectToolsPass::TOOLBOX_SERVICE . '.memory_factory', new Definition(\stdClass::class));

        foreach ([ListChannelsTool::class, UpdateChannelTool::class, UpdateProductTool::class, DeleteProductTool::class] as $class) {
            $container->setDefinition($class, (new Definition($class))->addTag('mcp.tool'));
        }

        return $container;
    }

    /** @return list<string> */
    private function registeredAdminMcpTools(ContainerBuilder $container): array
    {
        $names = [];
        foreach ($container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE . '.memory_factory')->getMethodCalls() as [$method, $arguments]) {
            self::assertSame('addTool', $method);
            $names[] = $arguments[1];
        }

        return $names;
    }

    /** @return list<string> */
    private function toolbox(ContainerBuilder $container): array
    {
        $tools = $container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE)->getArgument(0);
        self::assertSame([], $container->getDefinition('ai.toolbox.abstract')->getArguments()[0] instanceof TaggedIteratorArgument ? [] : ['parent changed'], 'The abstract toolbox is left alone.');
        self::assertIsArray($tools);
        self::assertContainsOnlyInstancesOf(Reference::class, $tools);

        return array_map(static fn (Reference $tool): string => (string) $tool, $tools);
    }
}

#[McpTool(name: 'list_channels', description: 'Lists channels')]
final class ListChannelsTool
{
    public function __invoke(): void
    {
    }
}

#[McpTool(name: 'update_channel', description: 'Updates a channel')]
final class UpdateChannelTool
{
    public function __invoke(): void
    {
    }
}

#[McpTool(name: 'update_product', description: 'Updates a product')]
final class UpdateProductTool
{
    public function __invoke(): void
    {
    }
}

#[McpTool(name: 'delete_product', description: 'Deletes a product')]
final class DeleteProductTool
{
    public function __invoke(): void
    {
    }
}

#[AsTool(name: 'own_tool', description: 'An own tool')]
final class OwnTool
{
    public function __invoke(): string
    {
        return '';
    }
}
