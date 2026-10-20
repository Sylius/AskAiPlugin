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
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class CollectToolsPassTest extends TestCase
{
    public function testTheToolboxGetsTheToolsMatchingThePrefixesExceptTheExcludedOnes(): void
    {
        $container = $this->container(
            readOnlyPrefixes: ['list_'],
            writePrefixes: ['update_'],
            exclude: ['update_channel'],
        );

        (new CollectToolsPass())->process($container);

        self::assertSame(['list_channels', 'update_product'], $this->exposedTools($container));
    }

    public function testNothingIsExcludedByDefault(): void
    {
        $container = $this->container(readOnlyPrefixes: ['list_'], writePrefixes: ['update_'], exclude: []);

        (new CollectToolsPass())->process($container);

        self::assertSame(['list_channels', 'update_channel', 'update_product'], $this->exposedTools($container));
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

        $container->setDefinition(CollectToolsPass::TOOLBOX_SERVICE, (new Definition(\stdClass::class))->setArguments([[]]));
        $container->setDefinition(CollectToolsPass::TOOLBOX_SERVICE . '.memory_factory', new Definition(\stdClass::class));

        foreach ([ListChannelsTool::class, UpdateChannelTool::class, UpdateProductTool::class, DeleteProductTool::class] as $class) {
            $container->setDefinition($class, (new Definition($class))->addTag('mcp.tool'));
        }

        return $container;
    }

    /** @return list<string> */
    private function exposedTools(ContainerBuilder $container): array
    {
        $names = [];
        foreach ($container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE . '.memory_factory')->getMethodCalls() as [$method, $arguments]) {
            self::assertSame('addTool', $method);
            $names[] = $arguments[1];
        }

        $toolbox = $container->getDefinition(CollectToolsPass::TOOLBOX_SERVICE)->getArgument(0);
        self::assertContainsOnlyInstancesOf(Reference::class, $toolbox);
        self::assertCount(\count($names), $toolbox, 'The toolbox holds exactly the registered tools.');

        return $names;
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
