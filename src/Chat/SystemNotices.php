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

namespace Sylius\AskAiPlugin\Chat;

final class SystemNotices
{
    private const HEADER = '[System notices — not written by the administrator]';

    /**
     * @param list<string> $notices
     * @param string       $then    what follows the notices: the administrator's message, or an instruction
     */
    public static function prompt(array $notices, string $then = ''): string
    {
        return rtrim(sprintf("%s\n- %s\n\n%s", self::HEADER, implode("\n- ", $notices), $then));
    }

    private function __construct()
    {
    }
}
