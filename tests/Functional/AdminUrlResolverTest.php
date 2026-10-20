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

namespace Tests\Sylius\AiAssistantPlugin\Functional;

use Sylius\AiAssistantPlugin\Navigation\AdminUrlResolver;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductTranslation;
use Sylius\Component\Customer\Model\CustomerGroup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminUrlResolverTest extends KernelTestCase
{
    private AdminUrlResolver $resolver;

    private int $productId;

    private int $groupId;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->resolver = self::getContainer()->get('test.sylius_ai_assistant.navigation.admin_url_resolver');

        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $product = new Product();
        $product->setCode('NAV_TEST_' . uniqid());
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $translation = new ProductTranslation();
        $translation->setLocale('en_US');
        $translation->setName('Nav test');
        $translation->setSlug('nav-test-' . uniqid());
        $product->addTranslation($translation);
        $group = new CustomerGroup();
        $group->setCode('nav_group_' . uniqid());
        $group->setName('Nav group');
        $em->persist($product);
        $em->persist($group);
        $em->flush();
        $this->productId = (int) $product->getId();
        $this->groupId = (int) $group->getId();
        $this->productCode = $product->getCode();
        $this->groupCode = $group->getCode();
    }

    private string $productCode;

    private string $groupCode;

    public function testOneResourceOpensItsShowOrEditPage(): void
    {
        self::assertSame("/admin/products/{$this->productId}", $this->resolver->resolve('products', $this->productCode));
        self::assertSame("/admin/products/{$this->productId}/edit", $this->resolver->resolve('products', $this->productCode, AdminUrlResolver::PAGE_EDIT));
        self::assertSame("/admin/customer-groups/{$this->groupId}/edit", $this->resolver->resolve('customer-groups', $this->groupCode), 'Without a show page, show falls back to edit.');
        self::assertNull($this->resolver->resolve('products', 'NO_SUCH_CODE'));
    }

    public function testTheApplicationAddsAPageAndSwitchesADefaultOneOff(): void
    {
        self::assertSame("/admin/products/{$this->productId}", $this->resolver->resolve('test-products', $this->productCode), 'A page added under sylius_ai_assistant.navigation.pages.');
        self::assertNull($this->resolver->resolve('exchange-rates', null, AdminUrlResolver::PAGE_LIST), 'A default page switched off with false.');
        self::assertSame("/admin/customer-groups/{$this->groupId}/edit", $this->resolver->resolve('customer-groups', $this->groupCode), 'The other defaults stay.');

        $described = implode("\n", $this->resolver->describePages());
        self::assertStringContainsString('test-products (identifier: code)', $described);
        self::assertStringNotContainsString('exchange-rates', $described);
    }

    public function testListPagesWithAndWithoutSearch(): void
    {
        self::assertSame('/admin/customer-groups/', $this->resolver->resolve('customer-groups', null, AdminUrlResolver::PAGE_LIST));
        self::assertSame('/admin/customer-groups/', $this->resolver->resolve('customer-groups', ''), 'An empty identifier means the list.');
        self::assertSame('/admin/products/?criteria%5Bsearch%5D%5Bvalue%5D=mug', $this->resolver->resolve('products', '', AdminUrlResolver::PAGE_LIST, 'mug'));
        self::assertSame('/admin/orders/?criteria%5Bnumber%5D%5Bvalue%5D=000000001', $this->resolver->resolve('orders', null, AdminUrlResolver::PAGE_LIST, '000000001'));
        self::assertSame('/admin/taxons/', $this->resolver->resolve('taxons', null, AdminUrlResolver::PAGE_LIST, 'ignored'), 'A grid without a search filter ignores the term.');
        self::assertNull($this->resolver->resolve('product-variants', null, AdminUrlResolver::PAGE_LIST), 'Nested resources have no list page.');
    }

    public function testIriOfAChangedResourceOpensItsEditPage(): void
    {
        self::assertSame("/admin/products/{$this->productId}", $this->resolver->resolveIri('/api/v2/admin/products/' . $this->productCode));
        self::assertSame("/admin/products/{$this->productId}/edit", $this->resolver->resolveIri('/api/v2/admin/products/' . $this->productCode, AdminUrlResolver::PAGE_EDIT));
    }

    public function testThePagesAreDescribedForTheModel(): void
    {
        $lines = $this->resolver->describePages();
        self::assertContains('products (identifier: code): show, edit, list (searchable)', $lines);
        self::assertContains('customer-groups (identifier: code): edit, list (searchable)', $lines);
        self::assertContains('product-variants (identifier: code): edit', $lines);
    }
}
