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

namespace Sylius\AiAssistantPlugin\Mcp;

/** Remembers which tool is executing, so a proposed write can be labelled with it; set by the toolbox listener. */
final class CurrentToolCall implements CurrentToolCallInterface
{
    private ?string $name = null;

    /** @var array<string, mixed>|null */
    private ?array $arguments = null;

    public function set(?string $name, ?array $arguments): void
    {
        $this->name = $name;
        $this->arguments = $arguments;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getArguments(): ?array
    {
        return $this->arguments;
    }
}
