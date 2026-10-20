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

namespace Sylius\AiAssistantPlugin\Factory;

use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\Resource\Factory\FactoryInterface;

final class AgentActionFactory implements AgentActionFactoryInterface
{
    /** @param FactoryInterface<AgentActionInterface> $decoratedFactory */
    public function __construct(
        private readonly FactoryInterface $decoratedFactory,
    ) {
    }

    public function createNew(): AgentActionInterface
    {
        return $this->decoratedFactory->createNew();
    }

    public function createProposal(
        ConversationInterface $conversation,
        string $httpMethod,
        string $path,
        ?array $body,
        ?string $toolName,
        ?array $toolArguments,
    ): AgentActionInterface {
        $action = $this->createNew();
        $action->setConversation($conversation);
        $action->setHttpMethod($httpMethod);
        $action->setPath($path);
        $action->setBody($body);
        $action->setToolName($toolName);
        $action->setToolArguments($toolArguments);

        return $action;
    }
}
