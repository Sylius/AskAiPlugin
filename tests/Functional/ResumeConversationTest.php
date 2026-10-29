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

use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class ResumeConversationTest extends WebTestCase
{
    use AdministratorTrait;
    use InteractsWithLiveComponents;

    private KernelBrowser $client;

    private AdminUser $admin;

    private Conversation $older;

    private Conversation $newer;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->admin = $this->createAdministrator('resume-test');
        $this->client->loginUser($this->admin, 'admin');

        $this->older = $this->createConversation($this->admin, 'Which orders are waiting to be shipped?');
        $this->newer = $this->createConversation($this->admin, 'What are the latest orders?');
    }

    public function testThePanelShowsTheNewestConversationUntilAnOlderOneIsResumed(): void
    {
        $this->client->request('GET', '/admin/ai-assistant/conversations');
        self::assertSame($this->newer->getId(), $this->panelConversationId());

        $this->resume($this->older);

        self::assertResponseRedirects(sprintf('/admin/ai-assistant/conversations/%d', $this->older->getId()));
        self::assertSame('1', $this->client->getCookieJar()->get('sylius_ai_assistant_open')?->getValue());

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame($this->older->getId(), $this->panelConversationId());
        self::assertSelectorTextContains('.ai-assistant__messages', 'Which orders are waiting to be shipped?');
    }

    public function testTheResumedConversationStaysInThePanelOnOtherPages(): void
    {
        $this->resume($this->older);
        $this->client->getCookieJar()->set(new Cookie('sylius_ai_assistant_open', '1'));

        $this->client->request('GET', '/admin/');

        self::assertResponseIsSuccessful();
        self::assertSame($this->older->getId(), $this->panelConversationId());
    }

    public function testANewChatTakesOverFromAResumedConversation(): void
    {
        $this->resume($this->older);
        $panel = $this->createLiveComponent('sylius_ai_assistant:panel', [], $this->client)->call('startConversation');
        $fresh = $panel->component()->conversationId;
        self::assertNotSame($this->older->getId(), $fresh);

        $this->client->getCookieJar()->set(new Cookie('sylius_ai_assistant_open', '1'));
        $this->client->request('GET', '/admin/');

        self::assertSame($fresh, $this->panelConversationId());
    }

    public function testTheGridOffersToResumeOnlyTheAdministratorsOwnConversations(): void
    {
        $other = $this->createAdministrator('resume-other');
        $foreign = $this->createConversation($other, 'Create a customer group called VIP');

        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations?criteria[adminUser]=%d', $this->admin->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('form[action$="/conversations/%d/resume"] button', $this->older->getId()));
        self::assertSelectorExists(sprintf('form[action$="/conversations/%d/resume"] button', $this->newer->getId()));

        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations?criteria[adminUser]=%d', $other->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('[data-test-resource-id="%d"]', $foreign->getId()));
        self::assertSelectorNotExists('form[action$="/resume"]');

        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations/%d', $foreign->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/resume"]');
    }

    public function testAnotherAdministratorsConversationCannotBeResumed(): void
    {
        $other = $this->createAdministrator('resume-other');
        $foreign = $this->createConversation($other, 'Create a customer group called VIP');

        $this->client->request('POST', sprintf('/admin/ai-assistant/conversations/%d/resume', $foreign->getId()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testResumingNeedsAValidCsrfToken(): void
    {
        $this->client->request('POST', sprintf('/admin/ai-assistant/conversations/%d/resume', $this->older->getId()), ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
    }

    public function testResumingWithoutARefererLandsOnTheDashboard(): void
    {
        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations/%d', $this->older->getId()));
        $token = (string) $this->client->getCrawler()->filter('form[action$="/resume"] input[name="_csrf_token"]')->attr('value');

        $this->client->request('POST', sprintf('/admin/ai-assistant/conversations/%d/resume', $this->older->getId()), ['_csrf_token' => $token], [], ['HTTP_REFERER' => '']);

        self::assertResponseRedirects('/admin/');
    }

    private function resume(Conversation $conversation): void
    {
        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations/%d', $conversation->getId()));
        $this->client->submitForm('Resume');
    }

    private function panelConversationId(): ?int
    {
        $props = json_decode((string) $this->client->getCrawler()->filter('.ai-assistant')->attr('data-live-props-value'), true, flags: \JSON_THROW_ON_ERROR);

        return $props['conversationId'];
    }

    private function createConversation(AdminUser $admin, string $question): Conversation
    {
        $conversation = new Conversation();
        $conversation->setAdminUser($this->entityManager()->find(AdminUser::class, $admin->getId()));
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ai_assistant.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_USER, $question);
        $this->entityManager()->persist($conversation);
        $this->entityManager()->flush();

        return $conversation;
    }
}
