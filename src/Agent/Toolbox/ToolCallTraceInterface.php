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

namespace Sylius\AiAssistantPlugin\Agent\Toolbox;

/** The tools called during the current turn, in order, for the administrator to see under the reply. */
interface ToolCallTraceInterface
{
    /** @param array<string, mixed> $arguments */
    public function record(string $id, string $name, array $arguments): void;

    public function count(): int;

    /** @return list<array{name: string, arguments: array<string, mixed>}> */
    public function drain(): array;
}
