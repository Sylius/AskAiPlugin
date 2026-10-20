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

namespace Sylius\AiAssistantPlugin\Navigation;

use Psr\Container\ContainerInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Maps an Admin API resource to the admin pages about it: the page of one resource (show,
 * or edit) and the list page, optionally filtered by the grid's search. The map is a
 * whitelist: the agent can only send the administrator to pages listed here.
 *
 * @phpstan-type PageSpec array{repository: string, field: string, show?: string, edit: string, index?: string, search?: string, parent?: array{param: string, path: string}}
 */
final class AdminUrlResolver implements AdminUrlResolverInterface
{
    /** @param array<string, PageSpec> $pages keyed by the Admin API path segment, e.g. "products" */
    public function __construct(
        private readonly array $pages,
        private readonly ContainerInterface $repositories,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function describePages(): array
    {
        $lines = [];
        foreach ($this->pages as $resource => $spec) {
            $pages = array_keys(array_filter([
                self::PAGE_SHOW => isset($spec['show']),
                self::PAGE_EDIT => true,
                self::PAGE_LIST => isset($spec['index']),
            ]));
            if (isset($spec['index'], $spec['search'])) {
                $pages[array_search(self::PAGE_LIST, $pages, true)] = 'list (searchable)';
            }
            $lines[] = sprintf('%s (identifier: %s): %s', $resource, $spec['field'], implode(', ', $pages));
        }

        return $lines;
    }

    public function resolveIri(string $iri, string $page = self::PAGE_SHOW): ?string
    {
        if (1 !== preg_match('#/admin/([a-z-]+)/([^/?\#]+)$#', $iri, $m)) {
            return null;
        }

        return $this->resolve($m[1], rawurldecode($m[2]), $page);
    }

    public function resolve(string $resource, ?string $identifier, string $page = self::PAGE_SHOW, ?string $search = null): ?string
    {
        $spec = $this->pages[$resource] ?? null;
        if (null === $spec) {
            return null;
        }

        if (self::PAGE_LIST === $page || null === $identifier || '' === $identifier) {
            return $this->resolveList($spec, $search);
        }

        $entity = $this->repositories->get($spec['repository'])->findOneBy([$spec['field'] => $identifier]);
        if (!$entity instanceof ResourceInterface) {
            return null;
        }

        $parameters = ['id' => $entity->getId()];
        if (isset($spec['parent'])) {
            $parameters[$spec['parent']['param']] = $this->propertyAccessor->getValue($entity, $spec['parent']['path']);
        }

        $route = self::PAGE_EDIT === $page ? $spec['edit'] : ($spec['show'] ?? $spec['edit']);

        return $this->urlGenerator->generate($route, $parameters);
    }

    /** @param PageSpec $spec */
    private function resolveList(array $spec, ?string $search): ?string
    {
        if (!isset($spec['index'])) {
            return null;
        }

        $parameters = [];
        if (null !== $search && '' !== trim($search) && isset($spec['search'])) {
            $parameters['criteria'] = [$spec['search'] => ['value' => trim($search)]];
        }

        return $this->urlGenerator->generate($spec['index'], $parameters);
    }
}
