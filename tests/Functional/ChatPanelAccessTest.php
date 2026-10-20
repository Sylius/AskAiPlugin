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

namespace Tests\Sylius\AiAssistantPlugin\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** The chat panel is for the administrators with the configured role (ROLE_API_ACCESS by default). */
final class ChatPanelAccessTest extends WebTestCase
{
    public function testThePanelIsOnTheAdminPagesOfAnAdministratorWithTheRole(): void
    {
        $client = self::createClient();
        $client->loginUser($this->administrator(['ROLE_API_ACCESS']), 'admin');

        $client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('sylius_ai_assistant:panel', (string) $client->getResponse()->getContent());
    }

    public function testThePanelIsNotThereWithoutTheRole(): void
    {
        $client = self::createClient();
        $client->loginUser($this->administrator([]), 'admin');

        $client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('sylius_ai_assistant:panel', (string) $client->getResponse()->getContent());
    }

    /** @param list<string> $roles */
    private function administrator(array $roles): AdminUser
    {
        $admin = new AdminUser();
        $admin->setUsername('access-test-' . uniqid());
        $admin->setEmail($admin->getUsername() . '@example.com');
        $admin->setPlainPassword('secret');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        foreach ($roles as $role) {
            $admin->addRole($role);
        }

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->persist($admin);
        $em->flush();

        return $admin;
    }
}
