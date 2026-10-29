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

namespace Sylius\AskAiPlugin\Provider;

use Sylius\AdminMcpServerPlugin\Provider\TokenProviderInterface;
use Sylius\Component\Core\Model\AdminUserInterface;

interface AdminUserTokenProviderInterface extends TokenProviderInterface
{
    public function actAs(?AdminUserInterface $admin): void;
}
