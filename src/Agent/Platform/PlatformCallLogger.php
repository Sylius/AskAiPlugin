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

namespace Sylius\AskAiPlugin\Agent\Platform;

use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\Event\InvocationEvent;
use Symfony\AI\Platform\Event\ResultConvertedEvent;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

final class PlatformCallLogger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function onInvocation(InvocationEvent $event): void
    {
        $input = $event->getInput();
        $options = $event->getOptions();

        $this->logger->debug('Calling the model', [
            'model' => $event->getModel()->getName(),
            'messages' => $input instanceof MessageBag ? \count($input->getMessages()) : null,
            'tools' => \is_array($options['tools'] ?? null) ? \count($options['tools']) : 0,
        ]);
    }

    public function onResultConverted(ResultConvertedEvent $event): void
    {
        $result = $event->getResult();
        $raw = $result->getRawResult()?->getData();

        $this->logger->debug('The model answered', [
            'model' => $event->getModel()->getName(),
            'result' => $result::class,
            'stop_reason' => \is_array($raw) ? ($raw['stop_reason'] ?? null) : null,
            'tool_calls' => $result instanceof ToolCallResult ? array_map(static fn ($call) => $call->getName(), $result->getContent()) : [],
            'text' => $result instanceof TextResult ? mb_substr($result->getContent(), 0, 200) : null,
        ]);
    }
}
