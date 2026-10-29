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

use Sylius\AskAiPlugin\Entity\AgentActionInterface;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\MessageInterface;

interface ConversationTimelineInterface
{
    /** @return list<MessageInterface|AgentActionInterface> */
    public function of(ConversationInterface $conversation): array;
}
