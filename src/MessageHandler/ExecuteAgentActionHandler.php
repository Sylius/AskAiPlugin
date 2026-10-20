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

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sylius\AiAssistantPlugin\Agent\AgentActionExecutorInterface;
use Sylius\AiAssistantPlugin\Chat\SystemNotices;
use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\AgentActionStates;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\AiAssistantPlugin\Message\ExecuteAgentAction;
use Sylius\AiAssistantPlugin\Message\RunAgentTurn;
use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolverInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Replays an approved action and lets the agent report the outcome. If the replay itself
 * cannot be processed, the conversation is failed with a notice instead of staying "running".
 */
final class ExecuteAgentActionHandler
{
    /** @param RepositoryInterface<AgentActionInterface> $actionRepository */
    public function __construct(
        private readonly RepositoryInterface $actionRepository,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly AgentActionExecutorInterface $executor,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly AdminUrlResolverInterface $urlResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ExecuteAgentAction $message): void
    {
        $action = $this->actionRepository->find($message->actionId);
        if (null === $action || AgentActionStates::STATE_APPROVED !== $action->getState()) {
            return;
        }

        $conversation = $action->getConversation();
        if (null === $conversation) {
            return;
        }

        try {
            $this->executor->execute($action);
        } catch (\Throwable $e) {
            $this->logger->error('Agent action could not be processed', ['action' => $action->getId(), 'exception' => $e]);
            $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_NOTICE, sprintf('Action #%d could not be processed: %s', $action->getId(), $e->getMessage()));
            $conversation->markFailed();
            $this->entityManager->flush();

            return;
        }

        $executed = AgentActionStates::STATE_EXECUTED === $action->getState();
        if ($executed) {
            $url = $this->pageOf($action);
            $this->logger->info('Agent action executed', ['action' => $action->getId(), 'navigate_to' => $url]);
            $conversation->setPendingNavigation($url);
        }
        $this->messageFactory->createForConversation(
            $conversation,
            MessageRoles::ROLE_NOTICE,
            $executed
                ? sprintf('Action #%d executed: %s', $action->getId(), $action->getLabel())
                : sprintf('Action #%d failed: %s', $action->getId(), (string) $action->getResult()),
        );
        $this->entityManager->flush();

        // Let the agent see the outcome and wrap up (e.g. report the created resource's code).
        $this->messageBus->dispatch(new RunAgentTurn(
            (int) $conversation->getId(),
            SystemNotices::prompt(
                [sprintf(
                    'Action #%d (%s) was %s by the administrator. Result: %s',
                    $action->getId(),
                    $action->getLabel(),
                    $executed ? 'approved and executed' : 'approved but FAILED',
                    $this->summarize($action),
                )],
                'Briefly tell the administrator the outcome; do not repeat the proposal.',
            ),
        ));
    }

    /**
     * The admin page of the resource the action created or changed, from the response's @id:
     * a created resource's own page, a changed resource's edit page (where the change shows).
     */
    private function pageOf(AgentActionInterface $action): ?string
    {
        $data = json_decode((string) $action->getResult(), true);
        $iri = \is_array($data) ? ($data['@id'] ?? null) : null;
        if (!\is_string($iri)) {
            return null;
        }

        return $this->urlResolver->resolveIri($iri, 'POST' === $action->getHttpMethod() ? AdminUrlResolverInterface::PAGE_SHOW : AdminUrlResolverInterface::PAGE_EDIT);
    }

    /**
     * The raw API response is a few kilobytes of JSON-LD; the model only needs the identity
     * of the resource and the fields the action set, as the API now reports them.
     */
    private function summarize(AgentActionInterface $action): string
    {
        $raw = (string) $action->getResult();
        $data = json_decode($raw, true);
        if (!\is_array($data)) {
            return mb_substr($raw, 0, 1500);
        }

        $summary = array_intersect_key($data, array_flip(['@id', 'id', 'code', 'tokenValue', 'state']));
        foreach (array_keys($action->getBody() ?? []) as $field) {
            if (\array_key_exists($field, $data)) {
                $summary[$field] = $data[$field];
            }
        }

        return json_encode($summary, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE) ?: mb_substr($raw, 0, 1500);
    }
}
