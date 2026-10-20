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

namespace Sylius\AiAssistantPlugin\Provider;

use Sylius\AdminMcpServerPlugin\Provider\TokenProviderInterface;
use Sylius\Component\Core\Model\AdminUserInterface;

/** An Admin MCP token provider that can mint the Admin API token of a given administrator. */
interface AdminUserTokenProviderInterface extends TokenProviderInterface
{
    /** Scope the following API calls to this administrator (null resets). */
    public function actAs(?AdminUserInterface $admin): void;
}
