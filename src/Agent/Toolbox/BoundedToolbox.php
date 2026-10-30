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

namespace Sylius\AskAiPlugin\Agent\Toolbox;

use Symfony\AI\Agent\Toolbox\ToolboxInterface;
use Symfony\AI\Agent\Toolbox\ToolResult;
use Symfony\AI\Agent\Toolbox\ToolResultConverter;
use Symfony\AI\Platform\Result\ToolCall;

final class BoundedToolbox implements ToolboxInterface
{
    public const NOTE = '[Result truncated: it was %d bytes, only the first %d are shown. Do not request more pages; narrow the query instead (filters, a smaller itemsPerPage, a more specific tool).]';

    public function __construct(
        private readonly ToolboxInterface $inner,
        private readonly int $maxResultSize,
        private readonly ToolResultConverter $converter = new ToolResultConverter(),
    ) {
    }

    public function getTools(): array
    {
        return $this->inner->getTools();
    }

    public function execute(ToolCall $toolCall): ToolResult
    {
        $result = $this->inner->execute($toolCall);
        $text = $this->converter->convert($result);

        if (null === $text || \strlen($text) <= $this->maxResultSize) {
            return $result;
        }

        return new ToolResult(
            $result->getToolCall(),
            mb_strcut($text, 0, $this->maxResultSize) . "\n\n" . sprintf(self::NOTE, \strlen($text), $this->maxResultSize),
            $result->getSources(),
        );
    }
}
