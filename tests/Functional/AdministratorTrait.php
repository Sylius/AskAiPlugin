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

namespace Tests\Sylius\AskAiPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUser;

trait AdministratorTrait
{
    /** @param list<string> $roles */
    private function createAdministrator(string $prefix, array $roles = ['ROLE_API_ACCESS']): AdminUser
    {
        $admin = new AdminUser();
        $admin->setUsername($prefix . '-' . uniqid());
        $admin->setEmail($admin->getUsername() . '@example.com');
        $admin->setPlainPassword('secret');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        foreach ($roles as $role) {
            $admin->addRole($role);
        }

        $this->entityManager()->persist($admin);
        $this->entityManager()->flush();

        return $admin;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
