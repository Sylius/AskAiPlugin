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

use Sylius\Resource\Model\ResourceInterface;

interface MessageInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getConversation(): ?ConversationInterface;

    public function setConversation(?ConversationInterface $conversation): void;

    /** One of MessageRoles::ROLE_*. */
    public function getRole(): string;

    public function setRole(string $role): void;

    public function getContent(): string;

    public function setContent(string $content): void;

    /**
     * Tools the assistant called while producing this message, in order: list of {name, arguments}.
     * A trace for the administrator, not conversation history.
     *
     * @return list<array{name: string, arguments: array<string, mixed>}>
     */
    public function getToolCalls(): array;

    /** @param list<array{name: string, arguments: array<string, mixed>}> $toolCalls */
    public function setToolCalls(array $toolCalls): void;

    /**
     * Tool names with how many times each was called, in order of first use: "list_products ×3".
     *
     * @return array<string, int>
     */
    public function getToolCallCounts(): array;

    public function getCreatedAt(): \DateTimeImmutable;
}
