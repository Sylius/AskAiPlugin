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

namespace Sylius\AiAssistantPlugin\Agent;

use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolverInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

/**
 * Feeds the system prompt template its {pages} variable: the admin pages the agent may open, one per line.
 * The Symfony AI template renderer substitutes it on the platform invocation.
 */
final class AdminPagesInputProcessor implements InputProcessorInterface
{
    public const VARIABLE = 'pages';

    public function __construct(private readonly AdminUrlResolverInterface $resolver)
    {
    }

    public function processInput(Input $input): void
    {
        $options = $input->getOptions();
        $options['template_vars'] = array_merge(
            $options['template_vars'] ?? [],
            [self::VARIABLE => '- ' . implode("\n- ", $this->resolver->describePages())],
        );

        $input->setOptions($options);
    }
}
