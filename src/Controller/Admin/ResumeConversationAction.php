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

namespace Sylius\AiAssistantPlugin\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Repository\ConversationRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ResumeConversationAction
{
    public const OPEN_COOKIE = 'sylius_ai_assistant_open';

    /** @param ConversationRepositoryInterface<ConversationInterface> $conversationRepository */
    public function __construct(
        private readonly ConversationRepositoryInterface $conversationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RouterInterface $router,
        private readonly string $accessRole,
    ) {
    }

    public function __invoke(Request $request, int $id): Response
    {
        if (!$this->security->isGranted($this->accessRole)) {
            throw new AccessDeniedHttpException(sprintf('The AI assistant needs the "%s" role.', $this->accessRole));
        }

        $conversation = $this->conversationRepository->find($id);
        if (null === $conversation || $conversation->getAdminUser() !== $this->security->getUser()) {
            throw new NotFoundHttpException(sprintf('Conversation #%d not found.', $id));
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken((string) $id, (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $conversation->touch();
        $this->entityManager->flush();

        $response = new RedirectResponse($this->targetOf($request));
        $response->headers->setCookie(Cookie::create(self::OPEN_COOKIE, '1', httpOnly: false));

        return $response;
    }

    private function targetOf(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');
        $host = parse_url($referer, \PHP_URL_HOST);

        if ('' !== $referer && (null === $host || $host === $request->getHost())) {
            return $referer;
        }

        return $this->router->generate('sylius_admin_dashboard');
    }
}
