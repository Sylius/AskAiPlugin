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

namespace Sylius\AskAiPlugin\Twig\Extension;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class MarkdownExtension extends AbstractExtension
{
    private ?MarkdownConverter $converter = null;

    public function getFilters(): array
    {
        return [
            new TwigFilter('sylius_ask_ai_markdown', $this->render(...)),
        ];
    }

    public function render(string $markdown): Markup
    {
        return new Markup((string) $this->converter()->convert($markdown), 'UTF-8');
    }

    private function converter(): MarkdownConverter
    {
        if (null === $this->converter) {
            $environment = new Environment([
                'html_input' => 'escape',
                'allow_unsafe_links' => false,
                'max_nesting_level' => 20,
            ]);
            $environment->addExtension(new CommonMarkCoreExtension());
            $environment->addExtension(new GithubFlavoredMarkdownExtension());

            $this->converter = new MarkdownConverter($environment);
        }

        return $this->converter;
    }
}
