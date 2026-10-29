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

namespace Sylius\AskAiPlugin\Chat;

use Sylius\AskAiPlugin\Agent\ChatTurnContextInterface;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\MessageInterface;
use Sylius\AskAiPlugin\Entity\MessageRoles;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Platform\Message\MessageBag;

final class ConversationChat implements ConversationChatInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ChatTurnContextInterface $context,
    ) {
    }

    public function submit(ConversationInterface $conversation, ?string $prompt = null): ?string
    {
        $last = $conversation->getMessages()->last();
        if (null === $prompt && (!$last instanceof MessageInterface || MessageRoles::ROLE_USER !== $last->getRole())) {
            return null;
        }

        $this->context->enter($conversation);

        try {
            return $this->agent->call(MessageBag::forId($conversation->getUuid(), ...(null === $prompt ? [] : [$prompt])))->asText();
        } finally {
            $this->context->leave();
        }
    }
}
