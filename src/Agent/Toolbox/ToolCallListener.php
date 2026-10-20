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

use Sylius\AiAssistantPlugin\Mcp\CurrentToolCallInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;

/** When the agent calls a tool: label the write it may propose and add the call to the turn's trace. */
final class ToolCallListener
{
    public function __construct(
        private readonly CurrentToolCallInterface $currentToolCall,
        private readonly ToolCallTraceInterface $trace,
    ) {
    }

    public function onToolCallRequested(ToolCallRequested $event): void
    {
        $call = $event->getToolCall();

        $this->currentToolCall->set($call->getName(), $call->getArguments());
        $this->trace->record($call->getId(), $call->getName(), $call->getArguments());
    }
}
