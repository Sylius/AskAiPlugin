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

final class MessageRoles
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    /** Written by the panel (approval outcomes, failures); shown to the administrator and folded into the next prompt, never model-authored. */
    public const ROLE_NOTICE = 'notice';

    private function __construct()
    {
    }
}
