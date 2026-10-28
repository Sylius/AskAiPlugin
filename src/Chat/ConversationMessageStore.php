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
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\MessageInterface as MessageEntityInterface;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\AI\Agent\Exception\ConflictException;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\MessageStore\MessageStoreInterface;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\MessageInterface;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\UuidV7;

final class ConversationMessageStore implements MessageStoreInterface
{
    /** @param RepositoryInterface<ConversationInterface> $conversationRepository */
    public function __construct(
        private readonly RepositoryInterface $conversationRepository,
        private readonly ChatTurnContextInterface $context,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function load(AbstractUid&TimeBasedUidInterface $id): MessageBag
    {
        return MessageBag::forId($id, ...array_map(self::toPlatformMessage(...), $this->get($id)->getMessages()->toArray()));
    }

    public function save(MessageBag $messages): void
    {
        $conversation = $this->get($messages->getId());
        $ids = array_map(static fn (MessageInterface $message): string => $message->getId()->toRfc4122(), $messages->getMessages());
        $stored = [];
        $continued = [];
        foreach ($conversation->getMessages() as $message) {
            $stored[$message->getUuid()->toRfc4122()] = true;
            if (MessageRoles::ROLE_NOTICE !== $message->getRole()) {
                $continued[] = $message->getUuid()->toRfc4122();
            }
        }
        if ($continued !== array_values(array_intersect($ids, $continued))) {
            throw ConflictException::forMessages($messages);
        }

        $new = 0;
        $toolCalls = [];
        foreach ($messages->getMessages() as $message) {
            if (!$message instanceof AssistantMessage) {
                continue;
            }
            $toolCalls = [...$toolCalls, ...array_map(static fn (ToolCall $call): array => ['name' => $call->getName(), 'arguments' => $call->getArguments()], $message->getToolCalls())];
            $text = (string) $message->asText();
            if ('' === $text && $message->hasToolCalls()) {
                continue;
            }
            if (!isset($stored[$message->getId()->toRfc4122()])) {
                $reply = $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_ASSISTANT, '' !== $text ? $text : '(no answer)');
                $reply->setUuid(UuidV7::fromString($message->getId()->toRfc4122()));
                $reply->setToolCalls($toolCalls);
                ++$new;
            }
            $toolCalls = [];
        }

        if ($new > 0) {
            $this->entityManager->flush();
        }
    }

    public function delete(AbstractUid&TimeBasedUidInterface $id): void
    {
        $this->entityManager->remove($this->get($id));
        $this->entityManager->flush();
    }

    private function get(AbstractUid&TimeBasedUidInterface $id): ConversationInterface
    {
        $conversation = $this->context->getConversation();
        if (null !== $conversation && $conversation->getUuid()->equals($id)) {
            return $conversation;
        }

        return $this->conversationRepository->findOneBy(['uuid' => $id])
            ?? throw new RuntimeException(sprintf('There is no conversation "%s".', $id));
    }

    private static function toPlatformMessage(MessageEntityInterface $message): MessageInterface
    {
        $platformMessage = match ($message->getRole()) {
            MessageRoles::ROLE_ASSISTANT => Message::ofAssistant($message->getContent()),
            MessageRoles::ROLE_NOTICE => Message::ofUser(SystemNotices::prompt([$message->getContent()])),
            default => Message::ofUser($message->getContent()),
        };

        return $platformMessage->withId($message->getUuid());
    }
}
