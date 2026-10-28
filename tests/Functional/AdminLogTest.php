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
use Sylius\AiAssistantPlugin\Entity\AgentActionInterface;
use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\AgentActionFactoryInterface;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminLogTest extends WebTestCase
{
    use AdministratorTrait;

    private KernelBrowser $client;

    private AdminUser $admin;

    private Conversation $conversation;

    private AgentActionInterface $action;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $this->admin = $this->createAdministrator('log-test', []);

        $this->conversation = new Conversation();

        $this->conversation->setAdminUser($this->admin);
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ai_assistant.factory.message');
        /** @var AgentActionFactoryInterface $actions */
        $actions = self::getContainer()->get('sylius_ai_assistant.factory.agent_action');
        $messages->createForConversation($this->conversation, MessageRoles::ROLE_USER, 'Rename the mug to "Log test mug"');
        $assistant = $messages->createForConversation($this->conversation, MessageRoles::ROLE_ASSISTANT, 'I proposed renaming it (action pending).');
        $assistant->setToolCalls([['name' => 'update_product', 'arguments' => ['code' => 'MUG']]]);
        $this->action = $actions->createProposal($this->conversation, 'put', '/api/v2/admin/products/MUG', ['name' => 'Log test mug'], 'update_product', ['code' => 'MUG']);
        $this->action->reject($this->admin);
        $pending = $actions->createProposal($this->conversation, 'patch', '/api/v2/admin/products/MUG', ['enabled' => false], 'update_product', ['code' => 'MUG']);
        $em->persist($this->conversation);
        $em->persist($this->action);
        $em->persist($pending);
        $em->flush();

        $this->client->loginUser($this->admin, 'admin');
    }

    public function testTheConversationGridListsTheConversationWithItsTopic(): void
    {
        $this->client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'Conversations');
        self::assertSelectorTextContains('table', 'Rename the mug to "Log test mug"');
        self::assertSelectorTextContains('table', $this->admin->getUsername());
        self::assertSelectorNotExists('a.btn-primary[href$="/new"]', 'The log is read-only: no create button.');
    }

    public function testTheConversationPageShowsTheTimelineWithoutApproveButtons(): void
    {
        $this->client->request('GET', sprintf('/admin/ai-assistant/conversations/%d', $this->conversation->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test-timeline] .ai-assistant__message--user', 'Rename the mug');
        self::assertSelectorTextContains('[data-test-timeline] .ai-assistant__message--assistant', 'I proposed renaming it');
        self::assertSelectorTextContains('[data-test-timeline] .ai-assistant__tools', 'update_product');
        self::assertSelectorTextContains(sprintf('[data-test-timeline] [data-action-id="%d"]', $this->action->getId()), 'rejected');
        self::assertSelectorNotExists('[data-test-timeline] [data-live-action-param]', 'No live actions on the log page.');
    }

    public function testTheAgentActionGridAndPageShowTheAuditTrail(): void
    {
        $this->client->request('GET', '/admin/ai-assistant/agent-actions');

        self::assertResponseIsSuccessful('A proposed action, decided by nobody yet, renders too.');
        self::assertSelectorTextContains('h1.page-title', 'Agent actions');
        self::assertSelectorTextContains('table', 'update_product');
        self::assertSelectorTextContains('table', $this->admin->getUsername());
        self::assertSelectorTextContains('table', 'proposed');

        $this->client->request('GET', '/admin/ai-assistant/agent-actions?criteria[state]=rejected');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('table', 'proposed');

        $this->client->request('GET', sprintf('/admin/ai-assistant/agent-actions/%d', $this->action->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.page-body', 'PUT /api/v2/admin/products/MUG');
        self::assertSelectorTextContains('.page-body', '"name": "Log test mug"');
        self::assertSelectorTextContains('.page-body', $this->admin->getUsername());
    }

    public function testTheMenuLinksToTheLogBeforeConfiguration(): void
    {
        $crawler = $this->client->request('GET', '/admin/ai-assistant/conversations');

        self::assertResponseIsSuccessful();
        $links = $crawler->filter('.navbar a[href="/admin/ai-assistant/conversations"], .navbar a[href="/admin/ai-assistant/agent-actions"], .navbar a[href="/admin/channels/"]')->each(static fn ($node): string => $node->attr('href'));
        self::assertSame(['/admin/ai-assistant/conversations', '/admin/ai-assistant/agent-actions', '/admin/channels/'], array_values(array_unique($links)));
    }
}
