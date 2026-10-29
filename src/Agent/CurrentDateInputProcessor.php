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

namespace Sylius\AskAiPlugin\Agent;

use Psr\Clock\ClockInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\InputProcessorInterface;

final class CurrentDateInputProcessor implements InputProcessorInterface
{
    public const VARIABLE = 'now';

    public function __construct(
        private readonly ClockInterface $clock,
        private readonly ?string $timeZone = null,
    ) {
    }

    public function processInput(Input $input): void
    {
        $now = $this->clock->now();
        if (null !== $this->timeZone) {
            $now = $now->setTimezone(new \DateTimeZone($this->timeZone));
        }

        $options = $input->getOptions();
        $options['template_vars'] = array_merge(
            $options['template_vars'] ?? [],
            [self::VARIABLE => $now->format('l, Y-m-d (e)')],
        );

        $input->setOptions($options);
    }
}
