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

namespace Sylius\AiAssistantPlugin\Chat;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Symfony\AI\Platform\Message\AssistantMessage;

interface ConversationChatInterface
{
    /**
     * Runs one turn of the conversation: the prompt goes to the agent as the conversation's
     * administrator, the reply (and whatever the agent proposed on the way) is persisted.
     */
    public function submit(ConversationInterface $conversation, string $prompt): AssistantMessage;
}
