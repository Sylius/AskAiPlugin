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

namespace Sylius\AiAssistantPlugin\Doctrine\ORM;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Repository\ConversationRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\AdminUserInterface;

/**
 * @template T of ConversationInterface
 * @implements ConversationRepositoryInterface<T>
 */
class ConversationRepository extends EntityRepository implements ConversationRepositoryInterface
{
    public function findLastActiveOf(AdminUserInterface $adminUser): ?ConversationInterface
    {
        return $this->createQueryBuilder('o')
            ->addSelect('COALESCE(o.updatedAt, o.createdAt) AS HIDDEN lastActivity')
            ->andWhere('o.adminUser = :adminUser')
            ->setParameter('adminUser', $adminUser)
            ->orderBy('lastActivity', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
