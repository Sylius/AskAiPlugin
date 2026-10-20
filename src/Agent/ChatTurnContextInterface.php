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
 * which conversation collects its proposed actions. Empty outside a chat turn.
 */
interface ChatTurnContextInterface
{
    public function enter(ConversationInterface $conversation): void;

    public function leave(): void;

    public function getConversation(): ?ConversationInterface;

    public function getAdminUserId(): ?int;

    public function getConversationId(): ?int;

    public function isChatTurn(): bool;
}
