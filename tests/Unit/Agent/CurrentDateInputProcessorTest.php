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

namespace Tests\Sylius\AskAiPlugin\Unit\Agent;

use PHPUnit\Framework\TestCase;
use Sylius\AskAiPlugin\Agent\CurrentDateInputProcessor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\Component\Clock\MockClock;

final class CurrentDateInputProcessorTest extends TestCase
{
    public function testItRendersTheDateWithTheWeekdayAndTheTimeZone(): void
    {
        $input = new Input('model', new MessageBag(), ['template_vars' => ['pages' => '- products']]);

        (new CurrentDateInputProcessor(new MockClock('2026-10-28 09:15:00', 'Europe/Warsaw')))->processInput($input);

        self::assertSame([
            'pages' => '- products',
            'now' => 'Wednesday, 2026-10-28 (Europe/Warsaw)',
        ], $input->getOptions()['template_vars'], 'The date only: a time would change the prompt every minute and defeat prompt caching.');
    }

    public function testTheConfiguredTimeZoneDecidesWhatDayItIs(): void
    {
        $input = new Input('model', new MessageBag(), []);

        (new CurrentDateInputProcessor(new MockClock('2026-10-28 23:30:00', 'UTC'), 'Europe/Warsaw'))->processInput($input);

        self::assertSame('Thursday, 2026-10-29 (Europe/Warsaw)', $input->getOptions()['template_vars']['now']);
    }

    public function testTheDefaultPromptUsesTheVariable(): void
    {
        $prompt = (string) file_get_contents(__DIR__ . '/../../../config/prompts/system_prompt.txt');

        self::assertStringContainsString('{' . CurrentDateInputProcessor::VARIABLE . '}', $prompt);
    }
}
