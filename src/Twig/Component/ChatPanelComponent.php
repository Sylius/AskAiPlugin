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

namespace Sylius\AskAiPlugin\Twig\Component;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AskAiPlugin\Chat\ConversationTimelineInterface;
use Sylius\AskAiPlugin\Entity\AgentActionInterface;
use Sylius\AskAiPlugin\Entity\ConversationInterface;
use Sylius\AskAiPlugin\Entity\MessageInterface;
use Sylius\AskAiPlugin\Entity\MessageRoles;
use Sylius\AskAiPlugin\Factory\MessageFactoryInterface;
use Sylius\AskAiPlugin\Message\ExecuteAgentAction;
use Sylius\AskAiPlugin\Message\RunAgentTurn;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Webmozart\Assert\Assert;

final class ChatPanelComponent
{
    use ComponentToolsTrait;
    use DefaultActionTrait;

    public const SESSION_CONVERSATION = 'sylius_ask_ai.conversation';

    #[LiveProp]
    public ?int $conversationId = null;

    #[LiveProp(writable: true)]
    public string $draft = '';

    #[LiveProp(writable: true)]
    public bool $open = false;

    #[LiveProp]
    public int $pollInterval;

    /** @var list<string> */
    public readonly array $suggestions;

    /**
     * @param RepositoryInterface<ConversationInterface> $conversationRepository
     * @param RepositoryInterface<AgentActionInterface> $actionRepository
     * @param FactoryInterface<ConversationInterface> $conversationFactory
     * @param list<string> $suggestions
     */
    public function __construct(
        private readonly RepositoryInterface $conversationRepository,
        private readonly FactoryInterface $conversationFactory,
        private readonly MessageFactoryInterface $messageFactory,
        private readonly RepositoryInterface $actionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly ConversationTimelineInterface $timeline,
        int $pollInterval,
        array $suggestions,
        private readonly string $accessRole,
    ) {
        $this->pollInterval = $pollInterval;
        $this->suggestions = $suggestions;
    }

    public function mount(): void
    {
        $this->conversationId = $this->session()?->get(self::SESSION_CONVERSATION);
        if (null !== $this->getConversation()) {
            return;
        }

        $latest = $this->conversationRepository->findBy(['adminUser' => $this->getAdminUser()], ['id' => 'DESC'], 1);
        $this->conversationId = ($latest[0] ?? null)?->getId();
    }

    public function getConversation(): ?ConversationInterface
    {
        if (null === $this->conversationId) {
            return null;
        }

        $conversation = $this->conversationRepository->find($this->conversationId);
        if (null === $conversation || $conversation->getAdminUser() !== $this->getAdminUser()) {
            $this->conversationId = null;

            return null;
        }

        return $conversation;
    }

    /** @return list<MessageInterface|AgentActionInterface> */
    public function getTimeline(): array
    {
        $conversation = $this->getConversation();

        return null === $conversation ? [] : $this->timeline->of($conversation);
    }

    public function isRunning(): bool
    {
        return $this->getConversation()?->isRunning() ?? false;
    }

    #[LiveAction]
    public function toggle(): void
    {
        $this->open = !$this->open;
    }

    #[LiveAction]
    public function refresh(): void
    {
        $conversation = $this->getConversation();
        $url = $conversation?->getPendingNavigation();
        if (null === $conversation || null === $url) {
            return;
        }

        $conversation->setPendingNavigation(null);
        $this->entityManager->flush();

        $referer = (string) $this->requestStack->getCurrentRequest()?->headers->get('referer');
        if ('' !== $referer && self::samePage($referer, $url)) {
            return;
        }

        $this->dispatchBrowserEvent('ask-ai:navigate', ['url' => $url]);
    }

    #[LiveAction]
    public function send(): void
    {
        $content = trim($this->draft);
        if ('' === $content) {
            return;
        }

        $conversation = $this->getConversation();
        if (null === $conversation || $conversation->isRunning()) {
            return;
        }

        $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_USER, $content);
        $conversation->markRunning();
        $this->entityManager->flush();

        $this->draft = '';
        $this->messageBus->dispatch(new RunAgentTurn($conversation->getId()));
    }

    #[LiveAction]
    public function cancel(): void
    {
        $conversation = $this->getConversation();
        if (null === $conversation || !$conversation->isRunning()) {
            return;
        }

        $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_NOTICE, 'Turn cancelled by the administrator.');
        $conversation->markIdle();
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function approve(#[LiveArg] int $id): void
    {
        $conversation = $this->getConversation();
        $action = $this->findPendingAction($id);
        if (null === $conversation || null === $action) {
            return;
        }

        $action->approve($this->getAdminUser());
        $conversation->markRunning();
        $this->entityManager->flush();

        $this->messageBus->dispatch(new ExecuteAgentAction($id));
    }

    #[LiveAction]
    public function reject(#[LiveArg] int $id): void
    {
        $conversation = $this->getConversation();
        $action = $this->findPendingAction($id);
        if (null === $conversation || null === $action) {
            return;
        }

        $action->reject($this->getAdminUser());
        $this->messageFactory->createForConversation($conversation, MessageRoles::ROLE_NOTICE, sprintf('Action #%d rejected: %s', $id, $action->getLabel()));
        $this->entityManager->flush();
    }

    #[LiveAction]
    public function startConversation(): void
    {
        $conversation = $this->conversationFactory->createNew();
        $conversation->setAdminUser($this->getAdminUser());
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();

        $this->conversationId = $conversation->getId();
        $this->session()?->set(self::SESSION_CONVERSATION, $this->conversationId);
        $this->open = true;
    }

    private static function samePage(string $referer, string $url): bool
    {
        $path = static fn (string $u): string => rtrim((string) parse_url($u, \PHP_URL_PATH), '/') . '?' . (string) parse_url($u, \PHP_URL_QUERY);

        return $path($referer) === $path($url);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request?->hasSession() ? $request->getSession() : null;
    }

    private function findPendingAction(int $id): ?AgentActionInterface
    {
        $conversation = $this->getConversation();
        $action = $this->actionRepository->find($id);

        if (null === $conversation || null === $action || $action->getConversation() !== $conversation || !$action->isProposed()) {
            return null;
        }

        return $action;
    }

    private function getAdminUser(): AdminUserInterface
    {
        if (!$this->security->isGranted($this->accessRole)) {
            throw new AccessDeniedException(sprintf('Ask AI needs the "%s" role.', $this->accessRole));
        }

        $user = $this->security->getUser();
        Assert::isInstanceOf($user, AdminUserInterface::class);

        return $user;
    }
}
