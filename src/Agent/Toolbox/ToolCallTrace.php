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

/**
 * The tools called during the current turn, in order, for the administrator to see under the
 * reply. Filled from the toolbox events; drained by the message store when the reply is persisted.
 */
final class ToolCallTrace implements ToolCallTraceInterface
{
    /** @var array<string, array{name: string, arguments: array<string, mixed>}> keyed by call id */
    private array $calls = [];

    public function record(string $id, string $name, array $arguments): void
    {
        $this->calls[$id] ??= ['name' => $name, 'arguments' => $arguments];
    }

    public function count(): int
    {
        return \count($this->calls);
    }

    public function drain(): array
    {
        $calls = array_values($this->calls);
        $this->calls = [];

        return $calls;
    }
}
