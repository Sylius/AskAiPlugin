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

namespace Sylius\AiAssistantPlugin\Entity;

final class ConversationStates
{
    public const STATE_IDLE = 'idle';

    public const STATE_RUNNING = 'running';

    public const STATE_FAILED = 'failed';

    private function __construct()
    {
    }
}
