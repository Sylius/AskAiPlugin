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
use Sylius\AskAiPlugin\Entity\Conversation;
use Sylius\AskAiPlugin\Entity\ConversationStates;
use Sylius\AskAiPlugin\Entity\Message;
use Sylius\AskAiPlugin\Entity\MessageRoles;
use Sylius\AskAiPlugin\Factory\MessageFactoryInterface;
use Sylius\AskAiPlugin\Message\RunAgentTurn;
use Sylius\AskAiPlugin\MessageHandler\RunAgentTurnHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\AskAiPlugin\Ai\ScriptedPlatform;

final class FailedTurnTest extends KernelTestCase
{
    use AdministratorTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testAThrowingModelFailsTheConversationWithANotice(): void
    {
        $admin = $this->createAdministrator('failed-turn', []);
        $conversation = new Conversation();
        $conversation->setAdminUser($admin);
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ask_ai.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_USER, 'Hello?');
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ask_ai_test.platform');
        $platform->setScript(static function (): never {
            throw new \LogicException('model is down');
        });

        /** @var RunAgentTurnHandler $handler */
        $handler = self::getContainer()->get('test.sylius_ask_ai.message_handler.run_agent_turn');
        $handler(new RunAgentTurn((int) $conversation->getId()));

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Conversation::class, $conversation->getId());
        self::assertNotNull($reloaded);
        self::assertSame(ConversationStates::STATE_FAILED, $reloaded->getState());
        self::assertFalse($reloaded->isRunning());

        $messages = $reloaded->getMessages()->toArray();
        $last = end($messages);
        self::assertInstanceOf(Message::class, $last);
        self::assertSame(MessageRoles::ROLE_NOTICE, $last->getRole());
        self::assertStringContainsString('model is down', $last->getContent());
    }
}
