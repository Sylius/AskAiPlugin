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

namespace Tests\Sylius\AskAiPlugin\Ai;

use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    name: 'test_huge_result',
    description: 'Test suite only: returns a very large text. Never call it.',
)]
final class HugeResultTool
{
    public const SIZE = 200000;

    public function __invoke(): string
    {
        return str_repeat('{"code":"MUG_0001","name":"Mug"},', (int) ceil(self::SIZE / 34));
    }
}
