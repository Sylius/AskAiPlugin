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

use Sylius\AskAiPlugin\DependencyInjection\Compiler\CollectToolsPass;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;

final class SyliusAskAiExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sylius_ask_ai.poll_interval', $config['poll_interval']);
        $container->setParameter('sylius_ask_ai.suggestions', $config['suggestions']);
        $container->setParameter('sylius_ask_ai.prompt.additional_instructions', $config['prompt']['additional_instructions']);
        $container->setParameter('sylius_ask_ai.prompt.time_zone', $config['prompt']['time_zone']);
        $container->setParameter('sylius_ask_ai.tools.read_only_prefixes', $config['tools']['read_only_prefixes']);
        $container->setParameter('sylius_ask_ai.tools.write_prefixes', $config['tools']['write_prefixes']);
        $container->setParameter('sylius_ask_ai.tools.exclude', $config['tools']['exclude']);
        $container->setParameter('sylius_ask_ai.tools.max_result_size', $config['tools']['max_result_size']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');

        $pages = $config['navigation']['pages'];
        $container->setParameter('sylius_ask_ai.navigation.pages', $pages);
        $container->getDefinition('sylius_ask_ai.navigation.admin_url_resolver')
            ->replaceArgument(0, $pages)
            ->replaceArgument(1, ServiceLocatorTagPass::register($container, array_map(
                static fn (string $id): Reference => new Reference($id),
                array_combine(array_column($pages, 'repository'), array_column($pages, 'repository')),
            )))
        ;
    }

    public function prepend(ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $this->registerResources('sylius_ask_ai', $config['driver'], $config['resources'], $container);
        $container->setParameter('sylius_ask_ai.access.role', $config['access']['role']);
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config')))->load('grids.yaml');

        $this->prependDoctrineMappings($container);
        $this->prependDoctrineMigrations($container);

        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [__DIR__ . '/../../translations'],
            ],
        ]);

        $container->prependExtensionConfig('ai', [
            'platform' => [
                'anthropic' => [
                    'api_key' => '%env(string:default::ANTHROPIC_API_KEY)%',
                ],
            ],
            'agent' => [
                CollectToolsPass::AGENT_NAME => [
                    'platform' => 'ai.platform.anthropic',
                    'model' => 'claude-haiku-4-5?max_output_tokens=' . $config['max_output_tokens'],
                    'prompt' => ['file' => Configuration::DEFAULT_SYSTEM_PROMPT],
                    'tools' => true,
                    'max_tool_calls' => 50,
                    'message_store' => 'sylius_ask_ai.chat.message_store',
                    'fault_tolerant_toolbox' => false,
                ],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Sylius\AskAiPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SyliusAskAiPlugin/src/Migrations';
    }

    /** @return list<string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    private function prependDoctrineMappings(ContainerBuilder $container): void
    {
        $config = array_merge(...$container->getExtensionConfig('doctrine'));
        if (!isset($config['dbal'], $config['orm'])) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SyliusAskAiPlugin' => [
                        'type' => 'xml',
                        'dir' => \dirname(__DIR__, 2) . '/config/doctrine/',
                        'is_bundle' => false,
                        'prefix' => 'Sylius\AskAiPlugin\Entity',
                        'alias' => 'SyliusAskAi',
                    ],
                ],
            ],
        ]);
    }
}
