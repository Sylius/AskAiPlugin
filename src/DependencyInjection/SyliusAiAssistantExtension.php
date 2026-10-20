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

use Sylius\AiAssistantPlugin\DependencyInjection\Compiler\CollectToolsPass;
use Sylius\Bundle\CoreBundle\DependencyInjection\PrependDoctrineMigrationsTrait;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class SyliusAiAssistantExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    use PrependDoctrineMigrationsTrait;

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('sylius_ai_assistant.poll_interval', $config['poll_interval']);
        $container->setParameter('sylius_ai_assistant.tools.read_only_prefixes', $config['tools']['read_only_prefixes']);
        $container->setParameter('sylius_ai_assistant.tools.write_prefixes', $config['tools']['write_prefixes']);
        $container->setParameter('sylius_ai_assistant.tools.exclude', $config['tools']['exclude']);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.php');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Resources (and so the %sylius_ai_assistant.model.*.class% parameters) and the admin grids
        // are registered here, not in load(): SyliusGridBundle resolves its configuration before this
        // extension loads, and the grids reference those parameters.
        $config = $this->processConfiguration(new Configuration(), $container->getExtensionConfig($this->getAlias()));
        $this->registerResources('sylius_ai_assistant', $config['driver'], $config['resources'], $container);
        (new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config')))->load('grids.yaml');

        $this->prependDoctrineMappings($container);
        $this->prependDoctrineMigrations($container);

        $container->prependExtensionConfig('framework', [
            'translator' => [
                'paths' => [__DIR__ . '/../../translations'],
            ],
        ]);

        // The agent and its platform are symfony/ai-bundle's: the application overrides any of this under "ai"
        // (model, prompt file, max_tool_calls, http_client of the platform, …). Out of the box it is the Anthropic
        // API with ANTHROPIC_API_KEY. The toolbox the bundle builds is replaced by the Admin MCP tools in
        // CollectToolsPass; the plugin's processors are tagged for this agent in services.php.
        $container->prependExtensionConfig('ai', [
            'platform' => [
                'anthropic' => [
                    'api_key' => '%env(string:default::ANTHROPIC_API_KEY)%',
                ],
            ],
            'agent' => [
                CollectToolsPass::AGENT_NAME => [
                    'platform' => 'ai.platform.anthropic',
                    'model' => 'claude-haiku-4-5',
                    'prompt' => ['file' => Configuration::DEFAULT_SYSTEM_PROMPT],
                    'tools' => true,
                    'max_tool_calls' => 50,
                    'exclude_tool_messages' => true,
                    'fault_tolerant_toolbox' => false,
                ],
            ],
        ]);
    }

    protected function getMigrationsNamespace(): string
    {
        return 'Sylius\AiAssistantPlugin\Migrations';
    }

    protected function getMigrationsDirectory(): string
    {
        return '@SyliusAiAssistantPlugin/src/Migrations';
    }

    /** @return list<string> */
    protected function getNamespacesOfMigrationsExecutedBefore(): array
    {
        return [
            'Sylius\Bundle\CoreBundle\Migrations',
        ];
    }

    /** The models are mapped in config/doctrine (XML, mapped superclasses the resource bundle turns into entities). */
    private function prependDoctrineMappings(ContainerBuilder $container): void
    {
        $config = array_merge(...$container->getExtensionConfig('doctrine'));
        if (!isset($config['dbal'], $config['orm'])) {
            return;
        }

        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'SyliusAiAssistantPlugin' => [
                        'type' => 'xml',
                        'dir' => \dirname(__DIR__, 2) . '/config/doctrine/',
                        'is_bundle' => false,
                        'prefix' => 'Sylius\AiAssistantPlugin\Entity',
                        'alias' => 'SyliusAiAssistant',
                    ],
                ],
            ],
        ]);
    }
}
