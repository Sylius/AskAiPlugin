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

namespace Sylius\AskAiPlugin\DependencyInjection\Compiler;

use Mcp\Capability\Attribute\McpTool;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;

final class CollectToolsPass implements CompilerPassInterface
{
    public const AGENT_NAME = 'sylius_ask_ai';

    public const AGENT_SERVICE = 'ai.agent.' . self::AGENT_NAME;

    public const TOOLBOX_SERVICE = 'ai.toolbox.' . self::AGENT_NAME;

    public const TOOL_TAG = 'sylius_ask_ai.tool';

    private const TOOL_FACTORY_SERVICE = self::TOOLBOX_SERVICE . '.memory_factory';

    private const TOOL_METHOD = '__invoke';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::TOOLBOX_SERVICE) || !$container->hasDefinition(self::TOOL_FACTORY_SERVICE)) {
            return;
        }

        $toolbox = $container->getDefinition(self::TOOLBOX_SERVICE);

        $tools = [];
        foreach ($this->bundleTools($container, $this->currentTools($container, $toolbox)) as $reference) {
            $tools[(string) $reference] = $reference;
        }
        foreach (array_keys($container->findTaggedServiceIds(self::TOOL_TAG)) as $serviceId) {
            $tools[$serviceId] = new Reference($serviceId);
        }
        foreach ($this->adminMcpTools($container) as $serviceId) {
            $tools[$serviceId] = new Reference($serviceId);
        }

        $toolbox->replaceArgument(0, array_values($tools));
    }

    private function currentTools(ContainerBuilder $container, Definition $toolbox): mixed
    {
        try {
            return $toolbox->getArgument(0);
        } catch (OutOfBoundsException) {
        }

        if ($toolbox instanceof ChildDefinition && $container->hasDefinition($toolbox->getParent())) {
            return $container->getDefinition($toolbox->getParent())->getArgument(0);
        }

        return null;
    }

    /** @return list<Reference> */
    private function bundleTools(ContainerBuilder $container, mixed $argument): array
    {
        if ($argument instanceof TaggedIteratorArgument) {
            return array_map(
                static fn (string $id): Reference => new Reference($id),
                array_keys($container->findTaggedServiceIds($argument->getTag())),
            );
        }

        return \is_array($argument) ? array_values(array_filter($argument, static fn (mixed $tool): bool => $tool instanceof Reference)) : [];
    }

    /** @return list<string> */
    private function adminMcpTools(ContainerBuilder $container): array
    {
        /** @var list<string> $prefixes */
        $prefixes = array_merge(
            $container->getParameter('sylius_ask_ai.tools.read_only_prefixes'),
            $container->getParameter('sylius_ask_ai.tools.write_prefixes'),
        );
        /** @var list<string> $excluded */
        $excluded = $container->getParameter('sylius_ask_ai.tools.exclude');

        $factory = $container->getDefinition(self::TOOL_FACTORY_SERVICE);

        $services = [];
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
                $services[$name] = $serviceId;
            }
        }
        ksort($services);

        return array_values($services);
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
