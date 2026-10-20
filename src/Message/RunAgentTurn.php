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

namespace Sylius\AiAssistantPlugin\Message;

/**
 * Dispatched after the admin's message is stored; the worker produces the assistant reply.
 * With an explicit prompt (e.g. an action outcome) the turn runs on that text instead of
 * the last user message.
 */
final class RunAgentTurn
{
    public function __construct(
        public readonly int $conversationId,
        public readonly ?string $prompt = null,
    ) {
    }
}
