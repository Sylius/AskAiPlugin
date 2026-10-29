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

namespace Sylius\AskAiPlugin\Mcp;

interface CurrentToolCallInterface
{
    /** @param array<string, mixed>|null $arguments */
    public function set(?string $name, ?array $arguments): void;

    public function getName(): ?string;

    /** @return array<string, mixed>|null */
    public function getArguments(): ?array;
}
