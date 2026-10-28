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

namespace Sylius\AiAssistantPlugin\Provider;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Sylius\AdminMcpServerPlugin\Exception\NotAuthenticatedException;
use Sylius\AdminMcpServerPlugin\Provider\TokenProviderInterface;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

final class AdminUserTokenProvider implements AdminUserTokenProviderInterface
{
    private ?AdminUserInterface $actingAs = null;

    /** @var array<int, string> */
    private array $tokens = [];

    /** @param RepositoryInterface<AdminUserInterface> $adminUserRepository */
    public function __construct(
        private readonly TokenProviderInterface $inner,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RepositoryInterface $adminUserRepository,
        private readonly ChatTurnContextInterface $context,
    ) {
    }

    public function actAs(?AdminUserInterface $admin): void
    {
        $this->actingAs = $admin;
    }

    public function getToken(bool $forceRefresh = false): string
    {
        $admin = $this->actingAs ?? $this->resolveTurnAdmin();
        if (null === $admin) {
            return $this->inner->getToken($forceRefresh);
        }

        $id = (int) $admin->getId();
        if ($forceRefresh || !isset($this->tokens[$id])) {
            $this->tokens[$id] = $this->jwtManager->create($admin);
        }

        return $this->tokens[$id];
    }

    private function resolveTurnAdmin(): ?AdminUserInterface
    {
        $adminId = $this->context->getAdminUserId();
        if (null === $adminId) {
            return null;
        }

        $admin = $this->adminUserRepository->find($adminId);
        if (null === $admin || !$admin->isEnabled()) {
            throw new NotAuthenticatedException(sprintf('Administrator #%d is unknown or disabled.', $adminId));
        }

        return $admin;
    }
}
