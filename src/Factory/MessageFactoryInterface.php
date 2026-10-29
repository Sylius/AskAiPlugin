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

namespace Sylius\AskAiPlugin\Factory;

use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\MessageInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<MessageInterface>
 */
interface MessageFactoryInterface extends FactoryInterface
{
    public function createForConversation(ConversationInterface $conversation, string $role, string $content): MessageInterface;
}
