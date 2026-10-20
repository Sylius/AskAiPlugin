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

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AiAssistantPlugin\Agent\Toolbox\ToolCallTraceInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\MessageInterface as MessageEntityInterface;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Symfony\AI\Chat\ManagedStoreInterface;
use Symfony\AI\Chat\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;

/**
 * Symfony AI message store backed by one Conversation and its Message entities.
 *
 * The store is created per conversation (see ConversationChat). `load()` maps the persisted
 * user/assistant messages to a MessageBag; panel notices (role "notice") are not part of
 * the model-facing history, the handler folds them into the prompt. `save()` persists only
 * what the agent added since `load()`: the assistant reply, with the tools called during the
 * turn (from the trace). User messages are written by the panel when they are submitted, so
 * they are never duplicated here.
 */
final class ConversationMessageStore implements MessageStoreInterface, ManagedStoreInterface
{
    /** @var array<string, true> ids of the platform messages handed out by load() */
    private array $loaded = [];

    public function __construct(
        private readonly ConversationInterface $conversation,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly ToolCallTraceInterface $trace,
    ) {
    }

    public function setup(array $options = []): void
    {
        // The schema is managed by the plugin's Doctrine migrations.
    }

    public function drop(): void
    {
        foreach ($this->conversation->getMessages() as $message) {
            $this->entityManager->remove($message);
        }
        $this->conversation->getMessages()->clear();
        $this->entityManager->flush();
        $this->loaded = [];
    }

    public function load(): MessageBag
    {
        $messages = [];
        $this->loaded = [];
        foreach ($this->conversation->getMessages() as $message) {
            $mapped = $this->toPlatformMessage($message);
            if (null !== $mapped) {
                $messages[] = $mapped;
                $this->loaded[$mapped->getId()->toRfc4122()] = true;
            }
        }

        return new MessageBag(...$messages);
    }

    public function save(MessageBag $messages): void
    {
        // Processors may prepend to the bag (system prompt), so new messages are told apart by id, not position.
        foreach ($messages->getMessages() as $message) {
            if (!$message instanceof AssistantMessage || isset($this->loaded[$message->getId()->toRfc4122()])) {
                continue;
            }
            $this->loaded[$message->getId()->toRfc4122()] = true;

            $text = $message->asText();
            $entity = $this->messageFactory->createForConversation($this->conversation, MessageRoles::ROLE_ASSISTANT, null !== $text && '' !== $text ? $text : '(no answer)');
            $entity->setToolCalls($this->trace->drain());
        }

        $this->entityManager->flush();
    }

    private function toPlatformMessage(MessageEntityInterface $message): ?MessageInterface
    {
        return match ($message->getRole()) {
            MessageRoles::ROLE_USER => Message::ofUser($message->getContent()),
            MessageRoles::ROLE_ASSISTANT => Message::ofAssistant($message->getContent()),
            default => null,
        };
    }
}
