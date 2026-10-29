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

use Sylius\AskAiPlugin\Entity\Conversation;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class ChatPanelTest extends WebTestCase
{
    use AdministratorTrait;

    private KernelBrowser $client;

    private AdminUser $admin;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $this->admin = $this->createAdministrator('panel-test');

        $this->client->loginUser($this->admin, 'admin');
        $this->client->getCookieJar()->set(new Cookie('sylius_ask_ai_open', '1'));
    }

    public function testTheHeaderLinksToTheHistoryNextToTheNewChatButton(): void
    {
        $this->client->request('GET', '/admin/ask-ai/conversations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ask-ai__panel .card-header', 'Ask AI');
        self::assertSelectorExists('.ask-ai__panel .card-header a[href="/admin/ask-ai/conversations"]');
        self::assertSelectorTextContains('.ask-ai__panel .card-header button[data-live-action-param="startConversation"]', 'New chat');
    }

    public function testWithoutAConversationThePanelOffersToStartOne(): void
    {
        $this->client->request('GET', '/admin/ask-ai/conversations');

        self::assertSelectorTextContains('.ask-ai__messages', 'What would you like to do?');
        self::assertSelectorNotExists('.ask-ai__messages [data-ask-ai-suggestion]');
        self::assertSelectorTextContains('.ask-ai__panel .card-footer', 'Start conversation');
    }

    public function testAnEmptyConversationShowsTheSuggestionsAndTheComposer(): void
    {
        $conversation = new Conversation();
        $conversation->setAdminUser($this->admin);
        $this->entityManager()->persist($conversation);
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/ask-ai/conversations');

        self::assertSelectorTextContains('.ask-ai__messages', 'What would you like to do?');
        self::assertSelectorCount(3, '.ask-ai__messages [data-ask-ai-suggestion]');
        self::assertSelectorExists('.ask-ai__panel textarea.ask-ai__input[data-model="draft"]');
    }
}
