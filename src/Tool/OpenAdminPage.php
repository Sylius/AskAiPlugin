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

namespace Sylius\AiAssistantPlugin\Tool;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\AiAssistantPlugin\Agent\ChatTurnContextInterface;
use Sylius\AiAssistantPlugin\Entity\ConversationInterface;
use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolverInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'open_admin_page',
    description: 'open_admin_page(resource, identifier?, page?, search?) → opens an admin page in the administrator\'s browser; this is how you SHOW things (the administrator sees the page at once, do not repeat its content in the chat). `resource` is the Admin API collection name (products, orders, customers, customer-groups, promotions, taxons, …). `identifier` is what the API uses for one resource (product/promotion/taxon code, order tokenValue, customer id); leave it empty to open the list page. `page` is "show" (default), "edit" (the edit form) or "list". `search` filters the list page (e.g. part of a product name, an order number). Nothing is changed by this tool.',
)]
final class OpenAdminPage
{
    /** @param RepositoryInterface<ConversationInterface> $conversationRepository */
    public function __construct(
        private readonly AdminUrlResolverInterface $resolver,
        private readonly ChatTurnContextInterface $context,
        private readonly RepositoryInterface $conversationRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param string $identifier the resource's identifier; empty for the list page
     * @param string $page       "show", "edit" or "list"
     * @param string $search     term to filter the list page with
     */
    public function __invoke(string $resource, string $identifier = '', string $page = AdminUrlResolverInterface::PAGE_SHOW, string $search = ''): string
    {
        $conversation = $this->context->isChatTurn()
            ? $this->conversationRepository->find($this->context->getConversationId())
            : null;
        if (null === $conversation) {
            return $this->json(['status' => 'unavailable', 'message' => 'Navigation only works from the admin chat panel.']);
        }

        $page = \in_array($page, [AdminUrlResolverInterface::PAGE_SHOW, AdminUrlResolverInterface::PAGE_EDIT, AdminUrlResolverInterface::PAGE_LIST], true) ? $page : AdminUrlResolverInterface::PAGE_SHOW;
        $url = $this->resolver->resolve($resource, $identifier, $page, $search);
        if (null === $url) {
            return $this->json([
                'status' => 'not_found',
                'message' => '' === $identifier
                    ? sprintf('No list page for %s.', $resource)
                    : sprintf('No admin page for %s "%s".', $resource, $identifier),
                'pages' => $this->resolver->describePages(),
            ]);
        }

        $conversation->setPendingNavigation($url);
        $this->entityManager->flush();

        return $this->json(['status' => 'opening', 'url' => $url, 'message' => 'The page is being opened for the administrator; mention it in one short sentence.']);
    }

    /** @param array<string, mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
