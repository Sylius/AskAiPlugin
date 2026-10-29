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

use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

final class StoreInstructionsInputProcessor implements InputProcessorInterface
{
    public const VARIABLE = 'store_instructions';

    public function __construct(private readonly ?string $instructions)
    {
    }

    public function processInput(Input $input): void
    {
        $instructions = trim((string) $this->instructions);

        $options = $input->getOptions();
        $options['template_vars'] = array_merge(
            $options['template_vars'] ?? [],
            [self::VARIABLE => '' === $instructions ? '' : "\nInstructions from the store:\n" . $instructions . "\n"],
        );

        $input->setOptions($options);
    }
}
