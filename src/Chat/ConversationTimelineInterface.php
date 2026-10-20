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

use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\MessageInterface;

/** What happened in a conversation, in order: messages and the actions the agent proposed. */
interface ConversationTimelineInterface
{
    /** @return list<MessageInterface|AgentActionInterface> */
    public function of(ConversationInterface $conversation): array;
}
