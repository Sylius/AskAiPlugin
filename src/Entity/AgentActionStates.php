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

namespace Sylius\AskAiPlugin\Entity;

final class AgentActionStates
{
    public const STATE_PROPOSED = 'proposed';

    public const STATE_APPROVED = 'approved';

    public const STATE_REJECTED = 'rejected';

    public const STATE_EXECUTED = 'executed';

    public const STATE_FAILED = 'failed';

    private function __construct()
    {
    }
}
