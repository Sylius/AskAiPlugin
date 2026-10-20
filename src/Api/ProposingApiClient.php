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

namespace Sylius\AiAssistantPlugin\Api;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AdminMcpServerPlugin\Api\ApiClientInterface;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Factory\AgentActionFactoryInterface;
use Sylius\AiAssistantPlugin\Mcp\CurrentToolCallInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * The write gate. Inside a chat turn every write the tools attempt against the Admin API
 * is recorded as a proposed AgentAction instead of being sent; the tool (and so the
 * model) gets a JSON notice to relay to the administrator. Reads pass through, as does
 * everything outside a chat turn (OAuth clients, the action executor).
 */
final class ProposingApiClient implements ApiClientInterface
{
    /** @param RepositoryInterface<ConversationInterface> $conversationRepository */
    public function __construct(
        private readonly ApiClientInterface $inner,
        private readonly ChatTurnContextInterface $context,
        private readonly CurrentToolCallInterface $currentToolCall,
        private readonly RepositoryInterface $conversationRepository,
        private readonly AgentActionFactoryInterface $actionFactory,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $path, array $query = []): string
    {
        return $this->inner->get($path, $query);
    }

    public function post(string $path, array $body = []): string
    {
        return $this->context->isChatTurn()
            ? $this->propose('POST', $path, $body, snapshot: false)
            : $this->inner->post($path, $body);
    }

    public function put(string $path, array $body = []): string
    {
        return $this->context->isChatTurn()
            ? $this->propose('PUT', $path, $body, snapshot: true)
            : $this->inner->put($path, $body);
    }

    public function patch(string $path, array $body = []): string
    {
        return $this->context->isChatTurn()
            ? $this->propose('PATCH', $path, $body, snapshot: true)
            : $this->inner->patch($path, $body);
    }

    public function delete(string $path): string
    {
        return $this->context->isChatTurn()
            ? $this->propose('DELETE', $path, null, snapshot: true)
            : $this->inner->delete($path);
    }

    /** @param array<string, mixed>|null $body */
    private function propose(string $method, string $path, ?array $body, bool $snapshot): string
    {
        $conversation = $this->conversationRepository->find($this->context->getConversationId());
        if (null === $conversation) {
            throw new \RuntimeException('Chat turn refers to an unknown conversation.');
        }

        $action = $this->actionFactory->createProposal(
            $conversation,
            $method,
            $path,
            $body,
            $this->currentToolCall->getName(),
            $this->currentToolCall->getArguments(),
        );

        if ($snapshot) {
            try {
                $action->setSnapshotBefore($this->inner->get($path));
            } catch (\Throwable) {
                // The diff is a convenience; a missing snapshot must not block the proposal.
            }
        }

        $this->entityManager->persist($action);
        $this->entityManager->flush();

        return json_encode([
            'status' => 'awaiting_approval',
            'action_id' => $action->getId(),
            'action' => $action->getLabel(),
            'message' => sprintf(
                'Nothing was changed yet. Action #%d is waiting for the administrator to approve it in the chat panel. ' .
                'Do not retry it; tell the administrator what you proposed and continue with the rest of the request.',
                $action->getId(),
            ),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
