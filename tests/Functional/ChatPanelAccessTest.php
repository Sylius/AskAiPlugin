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

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ChatPanelAccessTest extends WebTestCase
{
    use AdministratorTrait;

    public function testThePanelIsOnTheAdminPagesOfAnAdministratorWithTheRole(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createAdministrator('access-test'), 'admin');

        $client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('sylius_ai_assistant:panel', (string) $client->getResponse()->getContent());
    }

    public function testThePanelIsNotThereWithoutTheRole(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createAdministrator('access-test', []), 'admin');

        $client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('sylius_ai_assistant:panel', (string) $client->getResponse()->getContent());
    }
}
