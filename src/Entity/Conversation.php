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

namespace Sylius\AskAiPlugin\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Core\Model\AdminUserInterface;
use Symfony\Component\Uid\UuidV7;

class Conversation implements ConversationInterface
{
    protected ?int $id = null;

    protected UuidV7 $uuid;

    protected ?AdminUserInterface $adminUser = null;

    protected string $state = ConversationStates::STATE_IDLE;

    protected ?string $pendingNavigation = null;

    protected \DateTimeImmutable $createdAt;

    protected ?\DateTimeImmutable $updatedAt = null;

    /** @var Collection<int, MessageInterface> */
    protected Collection $messages;

    public function __construct()
    {
        $this->uuid = new UuidV7();
        $this->messages = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): UuidV7
    {
        return $this->uuid;
    }

    public function setUuid(UuidV7 $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getAdminUser(): ?AdminUserInterface
    {
        return $this->adminUser;
    }

    public function setAdminUser(?AdminUserInterface $adminUser): void
    {
        $this->adminUser = $adminUser;
    }

    public function getPendingNavigation(): ?string
    {
        return $this->pendingNavigation;
    }

    public function setPendingNavigation(?string $url): void
    {
        $this->pendingNavigation = $url;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function isRunning(): bool
    {
        return ConversationStates::STATE_RUNNING === $this->state;
    }

    public function markRunning(): void
    {
        $this->state = ConversationStates::STATE_RUNNING;
    }

    public function markIdle(): void
    {
        $this->state = ConversationStates::STATE_IDLE;
    }

    public function markFailed(): void
    {
        $this->state = ConversationStates::STATE_FAILED;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(MessageInterface $message): void
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
        }
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
