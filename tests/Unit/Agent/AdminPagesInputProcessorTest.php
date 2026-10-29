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
use Sylius\AskAiPlugin\Agent\AdminPagesInputProcessor;
use Sylius\AskAiPlugin\Navigation\AdminUrlResolverInterface;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\MessageBag;

final class AdminPagesInputProcessorTest extends TestCase
{
    public function testItSetsThePagesTemplateVariable(): void
    {
        $input = new Input('model', new MessageBag(), ['temperature' => 0.1]);

        (new AdminPagesInputProcessor($this->resolver()))->processInput($input);

        self::assertSame([
            'temperature' => 0.1,
            'template_vars' => ['pages' => "- product (identifier: code): show, edit\n- customer (identifier: id): list (searchable)"],
        ], $input->getOptions());
    }

    public function testItKeepsTheOtherTemplateVariables(): void
    {
        $input = new Input('model', new MessageBag(), ['template_vars' => ['store' => 'Fashion']]);

        (new AdminPagesInputProcessor($this->resolver()))->processInput($input);

        self::assertSame(['store', 'pages'], array_keys($input->getOptions()['template_vars']));
    }

    public function testTheDefaultPromptUsesTheVariable(): void
    {
        $prompt = (string) file_get_contents(__DIR__ . '/../../../config/prompts/system_prompt.txt');

        self::assertStringContainsString('{' . AdminPagesInputProcessor::VARIABLE . '}', $prompt);
        self::assertStringContainsString('"awaiting_approval"', $prompt);
    }

    private function resolver(): AdminUrlResolverInterface
    {
        $resolver = $this->createStub(AdminUrlResolverInterface::class);
        $resolver->method('describePages')->willReturn([
            'product (identifier: code): show, edit',
            'customer (identifier: id): list (searchable)',
        ]);

        return $resolver;
    }
}
