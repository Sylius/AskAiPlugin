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
use Sylius\AiAssistantPlugin\Agent\ChatTurnContext;
use Sylius\AiAssistantPlugin\Chat\ConversationChatInterface;
use Sylius\AiAssistantPlugin\Entity\AgentAction;
use Sylius\AiAssistantPlugin\Entity\AgentActionStates;
use Sylius\AiAssistantPlugin\Entity\Conversation;
use Sylius\AiAssistantPlugin\Entity\Message;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\AiAssistantPlugin\Ai\ScriptedPlatform;

/**
 * A full turn: the model asks for an Admin MCP tool, the
 * toolbox runs it in-process, the write gate turns the write into a proposed action, and
 * the model's final answer is persisted with the trace of what it called.
 */
final class InProcessAgentTurnTest extends KernelTestCase
{
    use AdministratorTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testAWriteToolCallBecomesAProposedActionAndTheAnswerIsPersisted(): void
    {
        $conversation = $this->createConversation();
        $calls = 0;
        $exposedTools = [];

        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ai_assistant_test.platform');
        $templateVars = [];
        $platform->setScript(static function (Model $model, array|string|object $input, array $options) use (&$calls, &$exposedTools, &$templateVars): ResultInterface {
            ++$calls;
            $exposedTools = array_map(static fn (array $tool): string => $tool['function']['name'], $options['tools'] ?? []);
            $templateVars = $options['template_vars'] ?? [];

            if (1 === $calls) {
                return new ToolCallResult([new ToolCall('call_1', 'update_product', ['code' => 'Ethereal_Drift_T_Shirt', 'body' => '{"name":"Renamed by the agent"}'])]);
            }

            return new TextResult('I proposed renaming the product; approve action #1 in the panel.');
        });

        /** @var ConversationChatInterface $chat */
        $chat = self::getContainer()->get('test.sylius_ai_assistant.chat');
        $reply = $chat->submit($conversation, 'Rename the T-shirt to "Renamed by the agent".');

        self::assertSame(2, $calls, 'The agent runs the tool and asks the model again with the result.');
        self::assertContains('update_product', $exposedTools);
        self::assertContains('list_products', $exposedTools);
        self::assertNotContains('delete_product', $exposedTools, 'Destructive tools are never exposed.');
        self::assertContains('open_admin_page', $exposedTools, "The assistant's own tool joins the Admin MCP tools.");
        self::assertStringContainsString('products (identifier: code)', $templateVars['pages'] ?? '', 'The prompt gets the pages the agent may open.');
        self::assertStringContainsString('Always answer in Polish.', $templateVars['store_instructions'] ?? '', 'The prompt gets the instructions of the store (test application config).');
        self::assertMatchesRegularExpression('/^[A-Z][a-z]+day, \d{4}-\d{2}-\d{2} \([A-Za-z_\/]+\)$/', $templateVars['now'] ?? '', 'The prompt gets the current date.');
        self::assertSame('I proposed renaming the product; approve action #1 in the panel.', $reply->asText());

        /** @var list<AgentAction> $actions */
        $actions = $this->entityManager->getRepository(AgentAction::class)->findBy(['conversation' => $conversation]);
        self::assertCount(1, $actions);
        self::assertSame(AgentActionStates::STATE_PROPOSED, $actions[0]->getState());
        self::assertSame('update_product', $actions[0]->getToolName());
        self::assertSame('PUT', $actions[0]->getHttpMethod());
        self::assertSame('products/Ethereal_Drift_T_Shirt', $actions[0]->getPath());

        $messages = $conversation->getMessages()->toArray();
        $last = end($messages);
        self::assertInstanceOf(Message::class, $last);
        self::assertSame(MessageRoles::ROLE_ASSISTANT, $last->getRole());
        self::assertSame([['name' => 'update_product', 'arguments' => ['code' => 'Ethereal_Drift_T_Shirt', 'body' => '{"name":"Renamed by the agent"}']]], $last->getToolCalls());
        self::assertSame(['name' => 'Renamed by the agent'], $actions[0]->getBody());

        /** @var ChatTurnContext $context */
        $context = self::getContainer()->get('test.sylius_ai_assistant.agent.context');
        self::assertFalse($context->isChatTurn(), 'The turn context is left after the turn.');
    }

    private function createConversation(): Conversation
    {
        $admin = $this->createAdministrator('agent-test');

        $conversation = new Conversation();

        $conversation->setAdminUser($admin);
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ai_assistant.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_USER, 'Rename the T-shirt to "Renamed by the agent".');
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }
}
