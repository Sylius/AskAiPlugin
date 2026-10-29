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

namespace Sylius\AskAiPlugin\MessageHandler;

use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Sylius\AskAiPlugin\Chat\ConversationChatInterface;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\MessageRoles;
use Sylius\AskAiPlugin\Factory\MessageFactoryInterface;
use Sylius\AskAiPlugin\Message\RunAgentTurn;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\AI\Agent\Exception\ConflictException;

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

        $conversation->markRunning();
        $this->doctrine->getManager()->flush();

        try {
            $this->logger->info('Running agent turn', ['conversation' => $conversation->getId()]);
            $this->chat->submit($conversation, $turn->prompt);
            $this->logger->info('Agent turn finished', ['conversation' => $conversation->getId()]);
            $conversation->markIdle();
            $this->doctrine->getManager()->flush();
        } catch (ConflictException $e) {
            $this->logger->warning('Agent turn abandoned, the conversation moved on', ['conversation' => $turn->conversationId, 'exception' => $e]);
        } catch (\Throwable $e) {
            $this->logger->error('Agent turn failed', ['conversation' => $turn->conversationId, 'exception' => $e]);
            $this->fail($turn->conversationId, $e);
        }
    }

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
}
