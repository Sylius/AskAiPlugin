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

namespace Sylius\AiAssistantPlugin\Chat;

use Sylius\AiAssistantPlugin\Entity\ConversationInterface;

interface ConversationChatInterface
{
    public function submit(ConversationInterface $conversation, ?string $prompt = null): ?string;
}
