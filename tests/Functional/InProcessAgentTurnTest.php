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
use Sylius\AskAiPlugin\Agent\ChatTurnContext;
use Sylius\AskAiPlugin\Agent\Toolbox\BoundedToolbox;
use Sylius\AskAiPlugin\Chat\ConversationChatInterface;
use Sylius\AskAiPlugin\Entity\AgentAction;
use Sylius\AskAiPlugin\Entity\AgentActionStates;
use Sylius\AskAiPlugin\Entity\Conversation;
use Sylius\AskAiPlugin\Entity\Message;
use Sylius\AskAiPlugin\Entity\MessageRoles;
use Sylius\AskAiPlugin\Factory\MessageFactoryInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\AskAiPlugin\Ai\HugeResultTool;
use Tests\Sylius\AskAiPlugin\Ai\ScriptedPlatform;

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
        $platform = self::getContainer()->get('sylius_ask_ai_test.platform');
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
        $chat = self::getContainer()->get('test.sylius_ask_ai.chat');
        $reply = $chat->submit($conversation);

        self::assertSame(2, $calls, 'The agent runs the tool and asks the model again with the result.');
        self::assertContains('update_product', $exposedTools);
        self::assertContains('list_products', $exposedTools);
        self::assertNotContains('delete_product', $exposedTools, 'Destructive tools are never exposed.');
        self::assertContains('open_admin_page', $exposedTools, "The assistant's own tool joins the Admin MCP tools.");
        self::assertStringContainsString('products (identifier: code)', $templateVars['pages'] ?? '', 'The prompt gets the pages the agent may open.');
        self::assertStringContainsString('Our product codes look like Ethereal_Drift_T_Shirt.', $templateVars['store_instructions'] ?? '', 'The prompt gets the instructions of the store (test application config).');
        self::assertMatchesRegularExpression('/^[A-Z][a-z]+day, \d{4}-\d{2}-\d{2} \([A-Za-z_\/]+\)$/', $templateVars['now'] ?? '', 'The prompt gets the current date.');
        self::assertSame('I proposed renaming the product; approve action #1 in the panel.', $reply);

        /** @var list<AgentAction> $actions */
        $actions = $this->entityManager->getRepository(AgentAction::class)->findBy(['conversation' => $conversation]);
        self::assertCount(1, $actions);
        self::assertSame(AgentActionStates::STATE_PROPOSED, $actions[0]->getState());
        self::assertSame('update_product', $actions[0]->getToolName());
        self::assertSame('PUT', $actions[0]->getHttpMethod());
        self::assertSame('products/Ethereal_Drift_T_Shirt', $actions[0]->getPath());

        $messages = $conversation->getMessages()->toArray();
        self::assertCount(2, $messages, 'The user message and the reply; tool calls and results stay in the trace.');
        $last = end($messages);
        self::assertInstanceOf(Message::class, $last);
        self::assertSame(MessageRoles::ROLE_ASSISTANT, $last->getRole());
        self::assertSame([['name' => 'update_product', 'arguments' => ['code' => 'Ethereal_Drift_T_Shirt', 'body' => '{"name":"Renamed by the agent"}']]], $last->getToolCalls());
        self::assertSame(['name' => 'Renamed by the agent'], $actions[0]->getBody());

        /** @var ChatTurnContext $context */
        $context = self::getContainer()->get('test.sylius_ask_ai.agent.context');
        self::assertFalse($context->isChatTurn(), 'The turn context is left after the turn.');
    }

    public function testAHugeToolResultIsCutBeforeTheModelSeesIt(): void
    {
        $conversation = $this->createConversation();
        $calls = 0;
        $toolResult = null;

        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ask_ai_test.platform');
        $platform->setScript(static function (Model $model, array|string|object $input) use (&$calls, &$toolResult): ResultInterface {
            ++$calls;

            if (1 === $calls) {
                return new ToolCallResult([new ToolCall('call_1', 'test_huge_result')]);
            }

            \assert(\is_array($input));
            foreach ($input['messages'] as $message) {
                if ('tool' === $message['role']) {
                    $toolResult = $message['content'];
                }
            }

            return new TextResult('That was too much.');
        });

        /** @var ConversationChatInterface $chat */
        $chat = self::getContainer()->get('test.sylius_ask_ai.chat');
        self::assertSame('That was too much.', $chat->submit($conversation));

        self::assertIsString($toolResult);
        self::assertStringEndsWith(sprintf(BoundedToolbox::NOTE, \strlen((new HugeResultTool())()), 50000), $toolResult);
        self::assertLessThan(HugeResultTool::SIZE, \strlen($toolResult));
        self::assertStringStartsWith(str_repeat('{"code":"MUG_0001","name":"Mug"},', 3), $toolResult);
    }

    public function testWhatTheModelSaysNextToAToolCallIsKeptWithThatCall(): void
    {
        $conversation = $this->createConversation();
        $calls = 0;

        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ask_ai_test.platform');
        $platform->setScript(static function () use (&$calls): ResultInterface {
            ++$calls;

            if (1 === $calls) {
                return new MultiPartResult([new TextResult('Renaming it now.'), new ToolCallResult([new ToolCall('call_1', 'update_product', ['code' => 'Ethereal_Drift_T_Shirt', 'body' => '{"name":"Renamed by the agent"}'])])]);
            }

            return new TextResult('Proposed, approve action #1.');
        });

        /** @var ConversationChatInterface $chat */
        $chat = self::getContainer()->get('test.sylius_ask_ai.chat');
        self::assertSame('Proposed, approve action #1.', $chat->submit($conversation));

        $messages = array_values($conversation->getMessages()->toArray());
        self::assertCount(3, $messages);
        self::assertSame('Renaming it now.', $messages[1]->getContent());
        self::assertSame(['update_product'], array_column($messages[1]->getToolCalls(), 'name'));
        self::assertSame('Proposed, approve action #1.', $messages[2]->getContent());
        self::assertSame([], $messages[2]->getToolCalls());
    }

    public function testATurnWithoutAPendingMessageDoesNotCallTheModel(): void
    {
        $conversation = $this->createConversation();
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ask_ai.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_ASSISTANT, 'Already answered.');
        $this->entityManager->flush();

        $calls = 0;
        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ask_ai_test.platform');
        $platform->setScript(static function () use (&$calls): ResultInterface {
            ++$calls;

            return new TextResult('A second answer.');
        });

        /** @var ConversationChatInterface $chat */
        $chat = self::getContainer()->get('test.sylius_ask_ai.chat');
        self::assertNull($chat->submit($conversation));
        self::assertSame(0, $calls);
        self::assertCount(2, $conversation->getMessages());
    }

    private function createConversation(): Conversation
    {
        $admin = $this->createAdministrator('agent-test');

        $conversation = new Conversation();

        $conversation->setAdminUser($admin);
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ask_ai.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_USER, 'Rename the T-shirt to "Renamed by the agent".');
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        return $conversation;
    }
}
