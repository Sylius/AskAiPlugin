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

namespace Sylius\AiAssistantPlugin\MessageHandler;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Sylius\AiAssistantPlugin\Chat\ConversationChatInterface;
use Sylius\AiAssistantPlugin\Chat\SystemNotices;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\AiAssistantPlugin\Message\RunAgentTurn;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * Runs one turn of a conversation. Whatever goes wrong (the model, a tool, the database),
 * the conversation never stays "running": it ends up idle with the reply, or failed with a
 * notice the administrator sees in the panel.
 */
final class RunAgentTurnHandler
{
    /** @param RepositoryInterface<ConversationInterface> $conversationRepository */
    public function __construct(
        private readonly RepositoryInterface $conversationRepository,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly ManagerRegistry $doctrine,
        private readonly ConversationChatInterface $chat,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunAgentTurn $turn): void
    {
        $conversation = $this->conversationRepository->find($turn->conversationId);
        if (null === $conversation) {
            return;
        }

        $prompt = $turn->prompt ?? $this->lastUserPrompt($conversation);
        if (null === $prompt) {
            $conversation->markIdle();
            $this->doctrine->getManager()->flush();

            return;
        }

        $conversation->markRunning();
        $this->doctrine->getManager()->flush();

        try {
            $this->logger->info('Running agent turn', ['conversation' => $conversation->getId()]);
            // The chat persists the assistant reply with the tool trace.
            $this->chat->submit($conversation, $prompt);
            $this->logger->info('Agent turn finished', ['conversation' => $conversation->getId()]);
            $conversation->markIdle();
            $this->doctrine->getManager()->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Agent turn failed', ['conversation' => $turn->conversationId, 'exception' => $e]);
            $this->fail($turn->conversationId, $e);
        }
    }

    /**
     * Records the failure on the conversation. A failed flush closes the entity manager, so it
     * is reset first and the conversation is loaded again.
     */
    private function fail(int $conversationId, \Throwable $e): void
    {
        $manager = $this->doctrine->getManager();
        if (method_exists($manager, 'isOpen') && !$manager->isOpen()) {
            $this->doctrine->resetManager();
            $manager = $this->doctrine->getManager();
        }

        $conversation = $this->conversationRepository->find($conversationId);
        if (null === $conversation) {
            return;
        }

        $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_NOTICE, sprintf('The assistant could not answer: %s', $e->getMessage()));
        $conversation->markFailed();
        $manager->flush();
    }

    /**
     * The last user message, preceded by whatever happened in the panel since the assistant
     * last spoke (rejected actions, failures) — the CLI session cannot see the UI otherwise.
     */
    private function lastUserPrompt(ConversationInterface $conversation): ?string
    {
        $user = null;
        $notices = [];
        foreach ($conversation->getMessages() as $message) {
            match ($message->getRole()) {
                MessageRoles::ROLE_ASSISTANT => [$user, $notices] = [null, []],
                MessageRoles::ROLE_NOTICE => $notices[] = $message->getContent(),
                default => $user = $message->getContent(),
            };
        }

        if (null === $user) {
            return null;
        }

        if ([] === $notices) {
            return $user;
        }

        return SystemNotices::prompt($notices, "[Administrator]\n" . $user);
    }
}
