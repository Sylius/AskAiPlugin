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
use Sylius\Resource\Model\ResourceInterface;

/**
 * A write the agent wanted to perform against the Admin API, held for a human decision.
 * Captured at the API client level (method + path + body), so it covers every tool.
 */
interface AgentActionInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getConversation(): ?ConversationInterface;

    public function setConversation(?ConversationInterface $conversation): void;

    /** One of AgentActionStates::STATE_*. */
    public function getState(): string;

    public function isProposed(): bool;

    public function getToolName(): ?string;

    public function setToolName(?string $toolName): void;

    /** @return array<string, mixed>|null */
    public function getToolArguments(): ?array;

    /** @param array<string, mixed>|null $toolArguments */
    public function setToolArguments(?array $toolArguments): void;

    public function getHttpMethod(): string;

    /** Stored upper-cased. */
    public function setHttpMethod(string $httpMethod): void;

    public function getPath(): string;

    public function setPath(string $path): void;

    /** @return array<string, mixed>|null */
    public function getBody(): ?array;

    /** @param array<string, mixed>|null $body */
    public function setBody(?array $body): void;

    public function getSnapshotBefore(): ?string;

    public function setSnapshotBefore(?string $snapshotBefore): void;

    public function getResult(): ?string;

    public function getDecidedBy(): ?AdminUserInterface;

    public function getDecidedAt(): ?\DateTimeImmutable;

    public function getCreatedAt(): \DateTimeImmutable;

    public function approve(AdminUserInterface $admin): void;

    public function reject(AdminUserInterface $admin): void;

    public function markExecuted(string $result): void;

    public function markFailed(string $error): void;

    /** Short human label: "update_product (PUT products/MUG)". */
    public function getLabel(): string;
}
