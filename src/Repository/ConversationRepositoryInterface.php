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

namespace Sylius\AiAssistantPlugin\Repository;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

/**
 * @template T of ConversationInterface
 * @extends RepositoryInterface<T>
 */
interface ConversationRepositoryInterface extends RepositoryInterface
{
    public function findLastActiveOf(AdminUserInterface $adminUser): ?ConversationInterface;
}
