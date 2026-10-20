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

namespace Sylius\AiAssistantPlugin\DependencyInjection\Compiler;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Gives the agent its toolbox: the Admin MCP tools whose names match the configured prefixes and are not
 * excluded by name, called in-process.
 *
 * The agent and its toolbox are symfony/ai-bundle's ("ai.agent.sylius_ai_assistant", prepended by the
 * extension). The bundle builds each toolbox with a MemoryToolFactory, the same way it registers tools listed
 * explicitly under "tools" with a name and a description; the pass feeds that factory the name and the
 * description of each `#[McpTool]` and makes the matching services the toolbox's tools.
 */
final class CollectToolsPass implements CompilerPassInterface
{
    public const AGENT_NAME = 'sylius_ai_assistant';

    public const AGENT_SERVICE = 'ai.agent.' . self::AGENT_NAME;

    public const TOOLBOX_SERVICE = 'ai.toolbox.' . self::AGENT_NAME;

    private const TOOL_FACTORY_SERVICE = self::TOOLBOX_SERVICE . '.memory_factory';

    private const TOOL_METHOD = '__invoke';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::TOOLBOX_SERVICE) || !$container->hasDefinition(self::TOOL_FACTORY_SERVICE)) {
            return;
        }

        /** @var list<string> $prefixes */
        $prefixes = array_merge(
            $container->getParameter('sylius_ai_assistant.tools.read_only_prefixes'),
            $container->getParameter('sylius_ai_assistant.tools.write_prefixes'),
        );

        /** @var list<string> $excluded */
        $excluded = $container->getParameter('sylius_ai_assistant.tools.exclude');

        $factory = $container->getDefinition(self::TOOL_FACTORY_SERVICE);

        $references = [];
        foreach (array_keys($container->findTaggedServiceIds('mcp.tool')) as $serviceId) {
            $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;
            if (!class_exists($class)) {
                continue;
            }

            foreach ((new \ReflectionClass($class))->getAttributes(McpTool::class) as $attribute) {
                /** @var McpTool $tool */
                $tool = $attribute->newInstance();
                $name = $tool->name ?? '';
                if (\in_array($name, $excluded, true) || !self::matches($name, $prefixes)) {
                    continue;
                }

                $factory->addMethodCall('addTool', [new Reference($serviceId), $name, $tool->description ?? '', self::TOOL_METHOD]);
                $references[$name] = new Reference($serviceId);
            }
        }
        ksort($references);

        $container->getDefinition(self::TOOLBOX_SERVICE)->replaceArgument(0, array_values($references));
    }

    /** @param list<string> $prefixes */
    private static function matches(string $name, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
