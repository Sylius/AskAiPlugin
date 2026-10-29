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

namespace Sylius\AskAiPlugin\Agent;

use Sylius\AdminMcpServerPlugin\Api\ApiClientInterface;
use Sylius\AskAiPlugin\Entity\AgentActionInterface;
use Sylius\AskAiPlugin\Entity\AgentActionStates;
use Sylius\AskAiPlugin\Provider\AdminUserTokenProviderInterface;

final class AgentActionExecutor implements AgentActionExecutorInterface
{
    public function __construct(
        private readonly ApiClientInterface $apiClient,
        private readonly AdminUserTokenProviderInterface $tokenProvider,
    ) {
    }

    public function execute(AgentActionInterface $action): void
    {
        $admin = $action->getDecidedBy();
        if (null === $admin || AgentActionStates::STATE_APPROVED !== $action->getState()) {
            throw new \LogicException(sprintf('Action #%d is not approved.', $action->getId() ?? 0));
        }

        $this->tokenProvider->actAs($admin);

        try {
            $result = match ($action->getHttpMethod()) {
                'POST' => $this->apiClient->post($action->getPath(), $action->getBody() ?? []),
                'PUT' => $this->apiClient->put($action->getPath(), $action->getBody() ?? []),
                'PATCH' => $this->apiClient->patch($action->getPath(), $action->getBody() ?? []),
                'DELETE' => $this->apiClient->delete($action->getPath()),
                default => throw new \LogicException(sprintf('Unsupported method %s.', $action->getHttpMethod())),
            };

            $action->markExecuted($result);
        } catch (\Throwable $e) {
            $action->markFailed($e->getMessage());
        } finally {
            $this->tokenProvider->actAs(null);
        }
    }
}
