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
use Sylius\AiAssistantPlugin\Entity\ConversationStates;
use Sylius\AiAssistantPlugin\Entity\Message;
use Sylius\AiAssistantPlugin\Entity\MessageRoles;
use Sylius\AiAssistantPlugin\Factory\MessageFactoryInterface;
use Sylius\AiAssistantPlugin\Message\RunAgentTurn;
use Sylius\AiAssistantPlugin\MessageHandler\RunAgentTurnHandler;
use Sylius\Component\Core\Model\AdminUser;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Sylius\AiAssistantPlugin\Ai\ScriptedPlatform;

/** A turn that blows up (any throwable, not only runtime exceptions) leaves the conversation failed, never running. */
final class FailedTurnTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testAThrowingModelFailsTheConversationWithANotice(): void
    {
        $admin = new AdminUser();
        $admin->setUsername('failed-turn-' . uniqid());
        $admin->setEmail($admin->getUsername() . '@example.com');
        $admin->setPlainPassword('secret');
        $admin->setLocaleCode('en_US');
        $admin->setEnabled(true);
        $this->entityManager->persist($admin);
        $conversation = new Conversation();
        $conversation->setAdminUser($admin);
        /** @var MessageFactoryInterface $messages */
        $messages = self::getContainer()->get('sylius_ai_assistant.factory.message');
        $messages->createForConversation($conversation, MessageRoles::ROLE_USER, 'Hello?');
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        /** @var ScriptedPlatform $platform */
        $platform = self::getContainer()->get('sylius_ai_assistant_test.platform');
        $platform->setScript(static function (): never {
            throw new \LogicException('model is down');
        });

        /** @var RunAgentTurnHandler $handler */
        $handler = self::getContainer()->get('test.sylius_ai_assistant.message_handler.run_agent_turn');
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
