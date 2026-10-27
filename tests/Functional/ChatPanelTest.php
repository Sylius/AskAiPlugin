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
use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/** The open chat panel, put together from the `sylius_ai_assistant.chat_panel` twig hooks. */
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
        $this->client->getCookieJar()->set(new Cookie('sylius_ai_assistant_open', '1'));
    }

    public function testTheHeaderLinksToTheHistoryNextToTheNewChatButton(): void
    {
        $this->client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ai-assistant__panel .card-header', 'AI assistant');
        self::assertSelectorExists('.ai-assistant__panel .card-header a[href="/admin/ai-assistant/conversations"]');
        self::assertSelectorTextContains('.ai-assistant__panel .card-header button[data-live-action-param="startConversation"]', 'New chat');
    }

    public function testWithoutAConversationThePanelOffersToStartOne(): void
    {
        $this->client->request('GET', '/admin/ai-assistant/conversations');

        self::assertSelectorTextContains('.ai-assistant__messages', 'What would you like to do?');
        self::assertSelectorNotExists('.ai-assistant__messages [data-ai-assistant-suggestion]');
        self::assertSelectorTextContains('.ai-assistant__panel .card-footer', 'Start conversation');
    }

    public function testAnEmptyConversationShowsTheSuggestionsAndTheComposer(): void
    {
        $conversation = new Conversation();
        $conversation->setAdminUser($this->admin);
        $this->entityManager()->persist($conversation);
        $this->entityManager()->flush();

        $this->client->request('GET', '/admin/ai-assistant/conversations');

        self::assertSelectorTextContains('.ai-assistant__messages', 'What would you like to do?');
        self::assertSelectorCount(3, '.ai-assistant__messages [data-ai-assistant-suggestion]');
        self::assertSelectorExists('.ai-assistant__panel textarea.ai-assistant__input[data-model="draft"]');
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        return $em;
    }
}
