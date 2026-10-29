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
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class ConversationTimeline implements ConversationTimelineInterface
{
    /** @param RepositoryInterface<AgentActionInterface> $actionRepository */
    public function __construct(
        private readonly RepositoryInterface $actionRepository,
    ) {
    }

    public function of(ConversationInterface $conversation): array
    {
        $items = array_merge(
            $conversation->getMessages()->toArray(),
            $this->actionRepository->findBy(['conversation' => $conversation], ['id' => 'ASC']),
        );
        usort($items, static fn (MessageInterface|AgentActionInterface $a, MessageInterface|AgentActionInterface $b): int => $a->getCreatedAt() <=> $b->getCreatedAt());

        return $items;
    }
}
