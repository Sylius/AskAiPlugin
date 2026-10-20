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

class Message implements MessageInterface
{
    protected ?int $id = null;

    protected ?ConversationInterface $conversation = null;

    protected string $role = MessageRoles::ROLE_USER;

    protected string $content = '';

    /** @var list<array{name: string, arguments: array<string, mixed>}>|null */
    protected ?array $toolCalls = null;

    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): ?ConversationInterface
    {
        return $this->conversation;
    }

    public function setConversation(?ConversationInterface $conversation): void
    {
        $this->conversation = $conversation;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): void
    {
        $this->role = $role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getToolCalls(): array
    {
        return $this->toolCalls ?? [];
    }

    public function setToolCalls(array $toolCalls): void
    {
        $this->toolCalls = [] === $toolCalls ? null : $toolCalls;
    }

    public function getToolCallCounts(): array
    {
        $counts = [];
        foreach ($this->getToolCalls() as $call) {
            $counts[$call['name']] = ($counts[$call['name']] ?? 0) + 1;
        }

        return $counts;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
