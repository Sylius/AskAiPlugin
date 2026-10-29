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

namespace Sylius\AskAiPlugin\Message;

final class RunAgentTurn
{
    public function __construct(
        public readonly int $conversationId,
        public readonly ?string $prompt = null,
    ) {
    }
}
