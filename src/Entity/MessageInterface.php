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

use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Uid\UuidV7;

interface MessageInterface extends ResourceInterface
{
    public function getId(): ?int;

    public function getUuid(): UuidV7;

    public function setUuid(UuidV7 $uuid): void;

    public function getConversation(): ?ConversationInterface;

    public function setConversation(?ConversationInterface $conversation): void;

    public function getRole(): string;

    public function setRole(string $role): void;

    public function getContent(): string;

    public function setContent(string $content): void;

    /** @return list<array{name: string, arguments: array<string, mixed>}> */
    public function getToolCalls(): array;

    /** @param list<array{name: string, arguments: array<string, mixed>}> $toolCalls */
    public function setToolCalls(array $toolCalls): void;

    public function getCreatedAt(): \DateTimeImmutable;
}
