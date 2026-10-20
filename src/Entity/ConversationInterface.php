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

namespace Sylius\AiAssistantPlugin\Entity;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Model\ResourceInterface;

interface ConversationInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getAdminUser(): ?AdminUserInterface;

    public function setAdminUser(?AdminUserInterface $adminUser): void;

    /** Admin URL the panel should navigate to on its next refresh (set by the agent). */
    public function getPendingNavigation(): ?string;

    public function setPendingNavigation(?string $url): void;

    public function getCreatedAt(): \DateTimeImmutable;

    public function getUpdatedAt(): ?\DateTimeImmutable;

    /** One of ConversationStates::STATE_*. */
    public function getState(): string;

    public function isRunning(): bool;

    public function markRunning(): void;

    public function markIdle(): void;

    public function markFailed(): void;

    /** @return Collection<int, MessageInterface> */
    public function getMessages(): Collection;

    public function addMessage(MessageInterface $message): void;
}
