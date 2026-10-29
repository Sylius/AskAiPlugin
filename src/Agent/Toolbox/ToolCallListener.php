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

namespace Sylius\AskAiPlugin\Agent\Toolbox;

use Sylius\AskAiPlugin\Mcp\CurrentToolCallInterface;
use Symfony\AI\Agent\Toolbox\Event\ToolCallRequested;

final class ToolCallListener
{
    public function __construct(
        private readonly CurrentToolCallInterface $currentToolCall,
    ) {
    }

    public function onToolCallRequested(ToolCallRequested $event): void
    {
        $call = $event->getToolCall();

        $this->currentToolCall->set($call->getName(), $call->getArguments());
    }
}
