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

namespace Sylius\AskAiPlugin\Factory;

use Sylius\AskAiPlugin\Entity\AgentActionInterface;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\Resource\Factory\FactoryInterface;

/**
 * @extends FactoryInterface<AgentActionInterface>
 */
interface AgentActionFactoryInterface extends FactoryInterface
{
    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed>|null $toolArguments
     */
    public function createProposal(
        ConversationInterface $conversation,
        string $httpMethod,
        string $path,
        ?array $body,
        ?string $toolName,
        ?array $toolArguments,
    ): AgentActionInterface;
}
