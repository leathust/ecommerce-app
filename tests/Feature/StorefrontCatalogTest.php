<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_is_resolved_by_slug_and_hidden_products_return_not_found(): void
    {
        $category = $this->category('phones');
        $published = $this->product($category, 'published-phone');
        $inactive = $this->product($category, 'inactive-phone', ['status' => false]);
        $hiddenCategoryProduct = $this->product($this->category('hidden', false), 'hidden-category-phone');

        $this->get(route('products.show', $published->slug))
            ->assertOk()
            ->assertSeeText('Sản phẩm published-phone')
            ->assertSeeText('SKU-PUBLISHED-PHONE');
        $this->get(route('products.show', $inactive->slug))->assertNotFound();
        $this->get(route('products.show', $hiddenCategoryProduct->slug))->assertNotFound();
        $this->get('/products/does-not-exist')->assertNotFound();
    }

    public function test_shop_filters_searches_sorts_and_paginates_published_products(): void
    {
        $phones = $this->category('phones');
        $laptops = $this->category('laptops');
        $brandA = $this->brand('brand-a');
        $brandB = $this->brand('brand-b');

        $match = $this->product($phones, 'matching-phone', ['brand_id' => $brandA->id, 'name_vi' => 'Điện thoại Alpha', 'price' => 200000]);
        $this->product($phones, 'other-brand-phone', ['brand_id' => $brandB->id, 'name_vi' => 'Điện thoại Beta', 'price' => 100000]);
        $this->product($laptops, 'matching-laptop', ['brand_id' => $brandA->id, 'name_vi' => 'Laptop Alpha', 'price' => 300000]);
        $this->product($phones, 'inactive-match', ['brand_id' => $brandA->id, 'name_vi' => 'Điện thoại Alpha ẩn', 'status' => false]);

        $this->get(route('shop', ['q' => 'Alpha', 'category' => 'phones', 'brand' => 'brand-a', 'sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeText($match->name_vi)
            ->assertDontSeeText('Điện thoại Beta')
            ->assertDontSeeText('Laptop Alpha')
            ->assertDontSeeText('Điện thoại Alpha ẩn')
            ->assertSee(route('products.show', $match->slug), false);

        $this->get(route('shop', ['sort' => 'price_asc']))
            ->assertOk()
            ->assertSeeInOrder(['Điện thoại Beta', 'Điện thoại Alpha', 'Laptop Alpha']);

        foreach (range(1, 13) as $number) {
            $this->product($phones, 'page-'.$number, ['brand_id' => $brandA->id, 'price' => $number * 1000]);
        }
        $page = $this->get(route('shop', ['category' => 'phones', 'per_page' => 12, 'page' => 2]));
        $page->assertOk()->assertSee('aria-current="page">2</span>', false);
    }

    public function test_shop_supports_multiple_categories_brands_price_range_and_ajax_partial(): void
    {
        $phones = $this->category('multi-phones');
        $laptops = $this->category('multi-laptops');
        $brandA = $this->brand('multi-a');
        $brandB = $this->brand('multi-b');
        $insideA = $this->product($phones, 'inside-a', ['brand_id' => $brandA->id, 'price' => 150000]);
        $insideB = $this->product($laptops, 'inside-b', ['brand_id' => $brandB->id, 'price' => 250000]);
        $this->product($phones, 'too-cheap', ['brand_id' => $brandA->id, 'price' => 50000]);
        $this->product($laptops, 'wrong-brand', ['price' => 200000]);

        $query = [
            'category' => ['multi-phones', 'multi-laptops'],
            'brand' => ['multi-a', 'multi-b'],
            'min_price' => 100000,
            'max_price' => 300000,
        ];
        $this->get(route('shop', $query))
            ->assertOk()
            ->assertSeeText($insideA->name_vi)
            ->assertSeeText($insideB->name_vi)
            ->assertDontSeeText('Sản phẩm too-cheap')
            ->assertDontSeeText('Sản phẩm wrong-brand')
            ->assertSee('name="category[]"', false)
            ->assertSee('data-price-filter', false)
            ->assertSee('data-catalog-loading', false);

        $this->getJson(route('shop', $query), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonStructure(['html', 'total'])
            ->assertJsonMissing(['filters', 'products']);
    }

    public function test_shop_rejects_invalid_filter_sort_page_size_and_inactive_relations(): void
    {
        $this->category('inactive-category', false);
        $this->brand('inactive-brand', false);

        $this->get(route('shop', [
            'category' => 'inactive-category',
            'brand' => 'inactive-brand',
            'sort' => 'popular',
            'per_page' => 999,
            'min_price' => 500000,
            'max_price' => 100000,
        ]))->assertSessionHasErrors(['category.0', 'brand.0', 'sort', 'per_page', 'max_price']);
    }

    public function test_product_detail_localizes_fields_and_uses_safe_fallbacks(): void
    {
        $category = $this->category('audio');
        $brand = $this->brand('sound-brand');
        $product = $this->product($category, 'speaker', [
            'brand_id' => $brand->id,
            'name_vi' => 'Loa dự phòng',
            'name_en' => 'English speaker',
            'description_en' => 'English catalog description',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/speaker.webp',
            'alt_text' => 'Speaker image',
            'is_primary' => true,
        ]);

        $this->withSession(['storefront_locale' => 'en'])
            ->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSeeText('English speaker')
            ->assertSeeText('English catalog description')
            ->assertSeeText('Category audio')
            ->assertSeeText('Brand sound-brand')
            ->assertSee('https://cdn.example.com/speaker.webp', false)
            ->assertDontSee('★★★★★', false)
            ->assertSee('Add to cart', false)
            ->assertSee(route('cart.items.store', ['storefrontProduct' => $product->slug]), false);
    }

    public function test_product_detail_shows_only_published_related_products_from_same_category(): void
    {
        $category = $this->category('related');
        $otherCategory = $this->category('other-related');
        $current = $this->product($category, 'current');
        foreach (range(1, 6) as $number) {
            $this->product($category, 'related-'.$number);
        }
        $this->product($category, 'related-hidden', ['status' => false]);
        $this->product($otherCategory, 'wrong-category');

        $response = $this->get(route('products.show', $current->slug))->assertOk();
        foreach (range(2, 6) as $number) {
            $response->assertSeeText('Sản phẩm related-'.$number);
        }
        $response->assertDontSeeText('Sản phẩm related-1')
            ->assertDontSeeText('Sản phẩm related-hidden')
            ->assertDontSeeText('Sản phẩm wrong-category');
    }

    public function test_legacy_products_page_redirects_to_shop_and_routes_use_storefront_locale(): void
    {
        $this->get(route('products'))->assertRedirect(route('shop'));

        $routes = collect(app('router')->getRoutes()->getRoutes());
        foreach (['shop', 'products', 'products.show'] as $name) {
            $route = $routes->first(fn ($route) => $route->getName() === $name);
            $this->assertNotNull($route);
            $this->assertContains('storefront.locale', $route->gatherMiddleware());
        }
    }

    private function category(string $slug, bool $status = true): Category
    {
        return Category::create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    private function brand(string $slug, bool $status = true): Brand
    {
        return Brand::create([
            'name_vi' => 'Thương hiệu '.$slug,
            'name_en' => 'Brand '.$slug,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(Category $category, string $slug, array $overrides = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.strtoupper($slug),
            'name_vi' => 'Sản phẩm '.$slug,
            'name_en' => 'Product '.$slug,
            'slug' => $slug,
            'description_vi' => 'Mô tả '.$slug,
            'description_en' => 'Description '.$slug,
            'price' => 100000,
            'quantity' => 10,
            'status' => true,
        ], $overrides));
    }
}
