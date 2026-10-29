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

namespace Sylius\AiAssistantPlugin\Twig\Extension;

use Sylius\AiAssistantPlugin\Chat\ConversationTimelineInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TimelineExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConversationTimelineInterface $timeline,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('sylius_ai_assistant_timeline', $this->timeline->of(...)),
        ];
    }
}
