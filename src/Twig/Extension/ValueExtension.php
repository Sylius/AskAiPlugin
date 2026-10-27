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

namespace Sylius\AiAssistantPlugin\Twig\Extension;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ValueExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('sylius_ai_assistant_value', $this->format(...)),
        ];
    }

    public function format(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        return json_encode($value, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }
}
