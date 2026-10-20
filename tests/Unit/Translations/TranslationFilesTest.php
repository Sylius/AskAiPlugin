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

namespace Tests\Sylius\AiAssistantPlugin\Unit\Translations;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/** Every locale file ships the same keys as the English one, and the templates use only keys that exist. */
final class TranslationFilesTest extends TestCase
{
    private const DIR = __DIR__ . '/../../../translations';

    public function testEveryLocaleHasTheSameKeysAsEnglish(): void
    {
        $english = $this->keys('en');

        foreach (glob(self::DIR . '/messages.*.yaml') ?: [] as $file) {
            $locale = explode('.', basename($file))[1];
            self::assertSame($english, $this->keys($locale), sprintf('messages.%s.yaml differs from messages.en.yaml', $locale));
        }
    }

    public function testTheTemplatesUseOnlyExistingKeys(): void
    {
        $keys = $this->keys('en');
        $used = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../../templates', \FilesystemIterator::SKIP_DOTS));
        foreach (new \RegexIterator($iterator, '/\.twig$/') as $template) {
            preg_match_all("/'(sylius_ai_assistant\\.ui\\.[a-z_.]+)'\\|trans/", (string) file_get_contents($template->getPathname()), $matches);
            $used = array_merge($used, $matches[1]);
        }

        self::assertNotEmpty($used);
        self::assertSame([], array_values(array_diff(array_unique($used), $keys)), 'Keys used in templates but missing from messages.en.yaml');
    }

    /** @return list<string> dotted keys, sorted */
    private function keys(string $locale): array
    {
        /** @var array<string, mixed> $tree */
        $tree = Yaml::parseFile(sprintf('%s/messages.%s.yaml', self::DIR, $locale));

        $flatten = static function (array $tree, string $prefix) use (&$flatten): array {
            $keys = [];
            foreach ($tree as $key => $value) {
                $keys = \is_array($value)
                    ? array_merge($keys, $flatten($value, $prefix . $key . '.'))
                    : [...$keys, $prefix . $key];
            }

            return $keys;
        };

        $keys = $flatten($tree, '');
        sort($keys);

        return $keys;
    }
}
