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

namespace Sylius\AiAssistantPlugin\Factory;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\MessageInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class MessageFactory implements MessageFactoryInterface
{
    /** @param FactoryInterface<MessageInterface> $decoratedFactory */
    public function __construct(
        private readonly FactoryInterface $decoratedFactory,
    ) {
    }

    public function createNew(): MessageInterface
    {
        return $this->decoratedFactory->createNew();
    }

    public function createForConversation(ConversationInterface $conversation, string $role, string $content): MessageInterface
    {
        $message = $this->createNew();
        $message->setConversation($conversation);
        $message->setRole($role);
        $message->setContent($content);
        $conversation->addMessage($message);

        return $message;
    }
}
