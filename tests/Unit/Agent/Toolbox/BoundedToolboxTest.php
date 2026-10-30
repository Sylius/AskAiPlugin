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

namespace Tests\Sylius\AskAiPlugin\Unit\Agent\Toolbox;

use PHPUnit\Framework\TestCase;
use Sylius\AskAiPlugin\Agent\Toolbox\BoundedToolbox;
use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

final class BoundedToolboxTest extends TestCase
{
    public function testAShortResultPassesThroughUntouched(): void
    {
        $call = new ToolCall('1', 'list_products');
        $result = new ToolResult($call, '{"hydra:member":[]}');

        self::assertSame($result, (new BoundedToolbox($this->inner($result), 100))->execute($call));
    }

    public function testALongResultIsCutAndTheModelIsToldToNarrowTheQuery(): void
    {
        $call = new ToolCall('1', 'list_products');
        $text = str_repeat('x', 1000);

        $bounded = (new BoundedToolbox($this->inner(new ToolResult($call, $text)), 100))->execute($call);

        self::assertSame($call, $bounded->getToolCall());
        self::assertSame(str_repeat('x', 100) . "\n\n" . sprintf(BoundedToolbox::NOTE, 1000, 100), $bounded->getResult());
    }

    public function testTheCutRespectsUtf8Characters(): void
    {
        $call = new ToolCall('1', 'get_product');
        $text = str_repeat('ż', 100);

        $bounded = (new BoundedToolbox($this->inner(new ToolResult($call, $text)), 101))->execute($call);

        self::assertStringStartsWith(str_repeat('ż', 50) . "\n\n", (string) $bounded->getResult());
    }

    public function testANonStringResultIsSerializedBeforeItIsMeasured(): void
    {
        $call = new ToolCall('1', 'count_orders');
        $small = (new BoundedToolbox($this->inner(new ToolResult($call, ['count' => 20])), 100))->execute($call);
        self::assertSame(['count' => 20], $small->getResult());

        $large = (new BoundedToolbox($this->inner(new ToolResult($call, array_fill(0, 100, 'order'))), 100))->execute($call);
        self::assertIsString($large->getResult());
        self::assertStringContainsString('Result truncated', $large->getResult());
    }

    public function testTheToolsAreTheInnerOnes(): void
    {
        $tools = [new Tool(new ExecutionReference(self::class), 'list_products', 'Lists products')];
        $inner = $this->createMock(ToolboxInterface::class);
        $inner->method('getTools')->willReturn($tools);

        self::assertSame($tools, (new BoundedToolbox($inner, 100))->getTools());
    }

    private function inner(ToolResult $result): ToolboxInterface
    {
        $inner = $this->createMock(ToolboxInterface::class);
        $inner->method('execute')->willReturn($result);

        return $inner;
    }
}
