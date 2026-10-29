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

namespace Sylius\AskAiPlugin\Navigation;

interface AdminUrlResolverInterface
{
    public const PAGE_SHOW = 'show';

    public const PAGE_EDIT = 'edit';

    public const PAGE_LIST = 'list';

    /** @return list<string> */
    public function describePages(): array;

    public function resolveIri(string $iri, string $page = self::PAGE_SHOW): ?string;

    public function resolve(string $resource, ?string $identifier, string $page = self::PAGE_SHOW, ?string $search = null): ?string;
}
