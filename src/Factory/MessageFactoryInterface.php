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

/**
 * @extends FactoryInterface<MessageInterface>
 */
interface MessageFactoryInterface extends FactoryInterface
{
    /** A message of the conversation, added to it; $role is one of MessageRoles::ROLE_*. */
    public function createForConversation(ConversationInterface $conversation, string $role, string $content): MessageInterface;
}
