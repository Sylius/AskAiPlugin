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

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallTraceInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Chat\Chat;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;

/**
 * Symfony AI Chat over the conversation entities: the shared agent (platform of choice +
 * the Admin MCP tools as its toolbox) on a message store backed by the conversation, run
 * inside the turn context that scopes the agent's API calls and proposals to it.
 */
final class ConversationChat implements ConversationChatInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatTurnContextInterface $context,
        private readonly ToolCallTraceInterface $trace,
    ) {
    }

    public function submit(ConversationInterface $conversation, string $prompt): AssistantMessage
    {
        $this->context->enter($conversation);
        $this->trace->drain();

        try {
            $chat = new Chat($this->agent, new ConversationMessageStore($conversation, $this->messageFactory, $this->entityManager, $this->trace));

            return $chat->submit(Message::ofUser($prompt));
        } finally {
            $this->context->leave();
        }
    }
}
