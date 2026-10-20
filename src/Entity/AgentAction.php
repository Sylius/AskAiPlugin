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

use Sylius\Component\Core\Model\AdminUserInterface;

class AgentAction implements AgentActionInterface
{
    protected ?int $id = null;

    protected ?ConversationInterface $conversation = null;

    protected string $state = AgentActionStates::STATE_PROPOSED;

    protected string $httpMethod = '';

    protected string $path = '';

    /** @var array<string, mixed>|null */
    protected ?array $body = null;

    protected ?string $toolName = null;

    /** @var array<string, mixed>|null */
    protected ?array $toolArguments = null;

    protected ?string $snapshotBefore = null;

    protected ?string $result = null;

    protected ?AdminUserInterface $decidedBy = null;

    protected ?\DateTimeImmutable $decidedAt = null;

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

    public function getState(): string
    {
        return $this->state;
    }

    public function isProposed(): bool
    {
        return AgentActionStates::STATE_PROPOSED === $this->state;
    }

    public function getToolName(): ?string
    {
        return $this->toolName;
    }

    public function setToolName(?string $toolName): void
    {
        $this->toolName = $toolName;
    }

    public function getToolArguments(): ?array
    {
        return $this->toolArguments;
    }

    public function setToolArguments(?array $toolArguments): void
    {
        $this->toolArguments = $toolArguments;
    }

    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): void
    {
        $this->httpMethod = strtoupper($httpMethod);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }

    public function getBody(): ?array
    {
        return $this->body;
    }

    public function setBody(?array $body): void
    {
        $this->body = $body;
    }

    public function getSnapshotBefore(): ?string
    {
        return $this->snapshotBefore;
    }

    public function setSnapshotBefore(?string $snapshotBefore): void
    {
        $this->snapshotBefore = $snapshotBefore;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function getDecidedBy(): ?AdminUserInterface
    {
        return $this->decidedBy;
    }

    public function getDecidedAt(): ?\DateTimeImmutable
    {
        return $this->decidedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function approve(AdminUserInterface $admin): void
    {
        $this->decide(AgentActionStates::STATE_APPROVED, $admin);
    }

    public function reject(AdminUserInterface $admin): void
    {
        $this->decide(AgentActionStates::STATE_REJECTED, $admin);
    }

    public function markExecuted(string $result): void
    {
        $this->state = AgentActionStates::STATE_EXECUTED;
        $this->result = $result;
    }

    public function markFailed(string $error): void
    {
        $this->state = AgentActionStates::STATE_FAILED;
        $this->result = $error;
    }

    public function getLabel(): string
    {
        $call = sprintf('%s %s', $this->httpMethod, $this->path);

        return null === $this->toolName ? $call : sprintf('%s (%s)', $this->toolName, $call);
    }

    protected function decide(string $state, AdminUserInterface $admin): void
    {
        if (!$this->isProposed()) {
            throw new \LogicException(sprintf('Action #%d was already %s.', $this->id ?? 0, $this->state));
        }

        $this->state = $state;
        $this->decidedBy = $admin;
        $this->decidedAt = new \DateTimeImmutable();
    }
}
