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

/**
 * Maps an Admin API resource to the admin pages about it. A whitelist: the agent can only
 * send the administrator to pages the resolver knows.
 */
interface AdminUrlResolverInterface
{
    public const PAGE_SHOW = 'show';

    public const PAGE_EDIT = 'edit';

    public const PAGE_LIST = 'list';

    /**
     * What can be opened per resource, for the model: "products: show, edit, list (searchable)".
     *
     * @return list<string>
     */
    public function describePages(): array;

    /** "/api/v2/admin/products/MUG" → URL of the product's page, null when unknown. */
    public function resolveIri(string $iri, string $page = self::PAGE_SHOW): ?string;

    /**
     * The page of one resource (show when the resource has one and $page is not "edit",
     * otherwise edit), or with $page "list" the list page, filtered by $search when given.
     */
    public function resolve(string $resource, ?string $identifier, string $page = self::PAGE_SHOW, ?string $search = null): ?string;
}
