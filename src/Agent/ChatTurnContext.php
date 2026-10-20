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

namespace Sylius\AiAssistantPlugin\Agent;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;

/**
 * Identity of the chat turn this process serves: which administrator the agent acts as and
 * which conversation collects its proposed actions. Entered by ConversationChat for the
 * duration of a turn; empty everywhere else (web, external MCP clients), which keeps the
 * Admin MCP plugin's own behaviour untouched.
 */
final class ChatTurnContext implements ChatTurnContextInterface
{
    private ?ConversationInterface $conversation = null;

    public function enter(ConversationInterface $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function leave(): void
    {
        $this->conversation = null;
    }

    public function getConversation(): ?ConversationInterface
    {
        return $this->conversation;
    }

    public function getAdminUserId(): ?int
    {
        return $this->conversation?->getAdminUser()?->getId();
    }

    public function getConversationId(): ?int
    {
        return $this->conversation?->getId();
    }

    public function isChatTurn(): bool
    {
        return null !== $this->conversation;
    }
}
