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
use Sylius\AskAiPlugin\Agent\StoreInstructionsInputProcessor;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\MessageBag;

final class StoreInstructionsInputProcessorTest extends TestCase
{
    public function testItRendersTheInstructionsAsASectionOfThePrompt(): void
    {
        $input = new Input('model', new MessageBag(), ['template_vars' => ['pages' => '- products']]);

        (new StoreInstructionsInputProcessor("Always answer in Polish.\nOur SKUs look like FW-1234."))->processInput($input);

        self::assertSame([
            'pages' => '- products',
            'store_instructions' => "\nInstructions from the store:\nAlways answer in Polish.\nOur SKUs look like FW-1234.\n",
        ], $input->getOptions()['template_vars']);
    }

    public function testNothingIsAddedWithoutInstructions(): void
    {
        foreach ([null, '', "  \n"] as $instructions) {
            $input = new Input('model', new MessageBag(), []);

            (new StoreInstructionsInputProcessor($instructions))->processInput($input);

            self::assertSame(['template_vars' => ['store_instructions' => '']], $input->getOptions());
        }
    }

    public function testTheDefaultPromptUsesTheVariable(): void
    {
        $prompt = (string) file_get_contents(__DIR__ . '/../../../config/prompts/system_prompt.txt');

        self::assertStringContainsString('{' . StoreInstructionsInputProcessor::VARIABLE . '}', $prompt);
    }
}
