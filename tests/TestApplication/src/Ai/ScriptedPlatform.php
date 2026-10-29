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

namespace Tests\Sylius\AiAssistantPlugin\Ai;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Test\MockPlatformFactory;

final class ScriptedPlatform implements PlatformInterface
{
    /** @var \Closure(Model, array|string|object, array): (\Symfony\AI\Platform\Result\ResultInterface|string) */
    private \Closure $script;

    public function __construct()
    {
        $this->script = static fn (): TextResult => new TextResult('No script set.');
    }

    /** @param \Closure(Model, array|string|object, array): (\Symfony\AI\Platform\Result\ResultInterface|string) $script */
    public function setScript(\Closure $script): void
    {
        $this->script = $script;
    }

    public function invoke(string|Model $model, array|string|object $input, array $options = []): DeferredResult
    {
        return MockPlatformFactory::createPlatform($this->script)->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return MockPlatformFactory::createPlatform($this->script)->getModelCatalog();
    }
}
