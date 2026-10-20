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

namespace Sylius\AiAssistantPlugin\Agent;

use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;

/** Replays an approved action against the Admin API and records the outcome on it. */
interface AgentActionExecutorInterface
{
    public function execute(AgentActionInterface $action): void;
}
