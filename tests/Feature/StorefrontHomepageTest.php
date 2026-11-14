<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\StorefrontHomeService;
use App\Support\HomePageData;
use App\Support\StorefrontProductPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontHomepageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(StorefrontHomeService::class)->forget();
    }

    protected function tearDown(): void
    {
        app(StorefrontHomeService::class)->forget();

        parent::tearDown();
    }

    public function test_product_storefront_scopes_are_explicit_and_do_not_change_admin_queries(): void
    {
        $activeCategory = $this->category('active', true);
        $hiddenCategory = $this->category('hidden', false);
        $published = $this->product($activeCategory, 'published', ['quantity' => 5, 'is_featured' => true]);
        $this->product($activeCategory, 'inactive', ['status' => false, 'quantity' => 9, 'is_featured' => true]);
        $this->product($hiddenCategory, 'hidden-category', ['quantity' => 7, 'is_featured' => true]);
        $this->product($activeCategory, 'empty-stock', ['quantity' => 0]);

        $this->assertSame(4, Product::query()->count());
        $this->assertEqualsCanonicalizing(
            [$published->id, Product::where('slug', 'empty-stock')->value('id')],
            Product::published()->pluck('id')->all(),
        );
        $this->assertSame([$published->id], Product::published()->inStock()->featured()->pluck('id')->all());
    }

    public function test_primary_image_relation_prefers_primary_then_sort_order_and_id(): void
    {
        $product = $this->product($this->category(), 'image-order');
        ProductImage::create(['product_id' => $product->id, 'image_url' => 'https://example.com/fallback.jpg', 'sort_order' => 0]);
        $expected = ProductImage::create(['product_id' => $product->id, 'image_url' => 'https://example.com/primary-last.jpg', 'sort_order' => 4, 'is_primary' => true]);
        ProductImage::create(['product_id' => $product->id, 'image_url' => 'https://example.com/primary-later.jpg', 'sort_order' => 8, 'is_primary' => true]);

        $loaded = Product::with('primaryImage')->findOrFail($product->id);

        $this->assertTrue($loaded->relationLoaded('primaryImage'));
        $this->assertSame($expected->id, $loaded->primaryImage?->id);
        $this->assertSame('https://example.com/primary-last.jpg', $loaded->primaryImage?->image_src);
    }

    public function test_presenter_localizes_falls_back_formats_and_builds_a_safe_card_payload(): void
    {
        $product = $this->product($this->category(), 'localized', [
            'name_vi' => '<script>Tên Việt</script>',
            'name_en' => '',
            'description_vi' => 'Mô tả Việt',
            'description_en' => '',
            'price' => 1234000,
            'quantity' => -3,
            'is_featured' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/product.webp',
            'alt_text' => '',
            'is_primary' => true,
        ]);

        $card = app(StorefrontProductPresenter::class)->present(
            Product::with('primaryImage')->findOrFail($product->id),
            'en',
        );

        $this->assertSame('<script>Tên Việt</script>', $card['name']);
        $this->assertSame('Mô tả Việt', $card['description']);
        $this->assertSame('https://cdn.example.com/product.webp', $card['image_url']);
        $this->assertSame($card['name'], $card['image_alt']);
        $this->assertSame('1.234.000 ₫', $card['formatted_price']);
        $this->assertSame(0, $card['quantity']);
        $this->assertSame('out_of_stock', $card['stock_status']);
        $this->assertSame('featured', $card['badge']);
        $this->assertStringContainsString('/products/localized', $card['url']);
        $this->assertStringContainsString('&lt;script&gt;', Blade::render('{{ $name }}', ['name' => $card['name']]));
    }

    public function test_presenter_does_not_lazy_load_images_and_uses_local_placeholder(): void
    {
        $product = $this->product($this->category(), 'no-image', ['name_en' => 'English name']);
        $presenter = app(StorefrontProductPresenter::class);

        // Warm the settings map before measuring presenter queries.
        $presenter->present($product, 'vi');
        DB::flushQueryLog();
        DB::enableQueryLog();
        $card = $presenter->present($product, 'en');

        $this->assertSame([], DB::getQueryLog());
        $this->assertSame('English name', $card['name']);
        $this->assertStringEndsWith('/assets/frontend/images/product-placeholder.svg', $card['image_url']);
        $this->assertFalse($product->relationLoaded('primaryImage'));
    }

    public function test_managed_product_image_uses_the_protected_media_route(): void
    {
        $product = $this->product($this->category(), 'managed-image');
        $image = ProductImage::create([
            'product_id' => $product->id,
            'image_url' => $product->id.'/abc123.webp',
            'is_primary' => true,
        ]);

        $this->assertSame(route('product-images.show', $image), $image->image_src);
    }

    public function test_home_service_selects_published_content_with_stable_limits_and_no_suggestion_duplicates(): void
    {
        $category = $this->category('home-active');
        $hiddenCategory = $this->category('home-hidden', false);
        $brand = $this->brand('home-brand');

        foreach (range(1, 12) as $position) {
            $product = $this->product($category, 'home-'.$position, [
                'brand_id' => $brand->id,
                'quantity' => $position === 12 ? 0 : 5,
                'is_featured' => $position <= 7,
                'created_at' => now()->subMinutes($position),
            ]);

            if ($position <= 8) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_url' => 'https://example.com/home-'.$position.'.jpg',
                    'is_primary' => true,
                ]);
            }
        }

        $this->product($category, 'home-inactive', ['status' => false, 'brand_id' => $brand->id]);
        $this->product($hiddenCategory, 'home-hidden-product', ['brand_id' => $brand->id]);

        $data = app(StorefrontHomeService::class)->home('vi');
        $trendingIds = array_column($data->trendingProducts, 'id');
        $suggestedIds = array_column($data->suggestedProducts, 'id');
        $allVisibleIds = array_merge(
            array_column($data->heroProducts, 'id'),
            $trendingIds,
            $suggestedIds,
        );

        $this->assertCount(6, $data->heroProducts);
        $this->assertCount(5, $data->trendingProducts);
        $this->assertCount(4, $data->suggestedProducts);
        $this->assertSame([], array_values(array_intersect($trendingIds, $suggestedIds)));
        $this->assertNotContains(Product::where('slug', 'home-inactive')->value('id'), $allVisibleIds);
        $this->assertNotContains(Product::where('slug', 'home-hidden-product')->value('id'), $allVisibleIds);
        $this->assertSame('home-active', $data->categories[0]['slug']);
        $this->assertSame(12, $data->categories[0]['product_count']);
        $this->assertSame('home-brand', $data->brands[0]['slug']);
        $this->assertSame(12, $data->brands[0]['product_count']);
    }

    public function test_home_service_returns_empty_collections_when_catalog_has_no_published_content(): void
    {
        $this->product($this->category('inactive-only'), 'inactive-only-product', ['status' => false]);

        $data = app(StorefrontHomeService::class)->home('en');

        $this->assertSame('en', $data->locale);
        $this->assertSame([], $data->heroProducts);
        $this->assertSame([], $data->trendingProducts);
        $this->assertSame([], $data->suggestedProducts);
        $this->assertSame([], $data->categoryTabs);
        $this->assertSame([], $data->categories);
        $this->assertSame([], $data->brands);
    }

    public function test_home_service_caches_each_locale_and_stays_within_query_budget(): void
    {
        $product = $this->product($this->category('cache-category'), 'cache-product');
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://example.com/cache.jpg',
            'is_primary' => true,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(StorefrontHomeService::class)->home('vi');
        $firstQueryCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        app(StorefrontHomeService::class)->home('vi');

        $this->assertLessThanOrEqual(10, $firstQueryCount);
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_catalog_model_changes_invalidate_cached_home_data(): void
    {
        $category = $this->category('invalidate-category');
        $brand = $this->brand('invalidate-brand');
        $product = $this->product($category, 'invalidate-product', ['brand_id' => $brand->id]);
        $service = app(StorefrontHomeService::class);

        $initial = $service->home('vi');
        $this->assertSame($product->name_vi, $initial->trendingProducts[0]['name']);
        $this->assertSame([], $initial->heroProducts);

        $product->update(['name_vi' => 'Updated product']);
        $this->assertSame('Updated product', $service->home('vi')->trendingProducts[0]['name']);

        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://example.com/invalidate.jpg',
            'is_primary' => true,
        ]);
        $this->assertCount(1, $service->home('vi')->heroProducts);

        $category->update(['name_vi' => 'Updated category']);
        $this->assertSame('Updated category', $service->home('vi')->categories[0]['name']);

        $brand->update(['name_vi' => 'Updated brand']);
        $this->assertSame('Updated brand', $service->home('vi')->brands[0]['name']);
    }

    public function test_home_controller_passes_the_home_page_dto_to_the_view(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertViewHas('homePage', fn ($data): bool => $data instanceof HomePageData);
    }

    public function test_home_renders_real_catalog_blocks_with_escaped_content_and_named_links(): void
    {
        $category = $this->category('render-category');
        $brand = $this->brand('render-brand');
        $product = $this->product($category, 'render-product', [
            'brand_id' => $brand->id,
            'name_vi' => '<script>Unsafe product</script>',
            'description_vi' => '<img src=x onerror=alert(1)>',
            'price' => 345000,
            'quantity' => 7,
            'is_featured' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/render.webp',
            'alt_text' => 'Safe product image',
            'is_primary' => true,
        ]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSeeText('Sản phẩm nổi bật')
            ->assertSee('&lt;script&gt;Unsafe product&lt;/script&gt;', false)
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertSee('https://cdn.example.com/render.webp', false)
            ->assertSee('345.000 ₫')
            ->assertSee(route('products.show', ['storefrontProduct' => 'render-product']), false)
            ->assertSee(route('shop', ['category' => 'render-category']), false)
            ->assertDontSee('product.html', false)
            ->assertDontSee('★★★★★', false)
            ->assertSee(route('newsletter.subscribe'), false)
            ->assertSee('data-newsletter-form', false)
            ->assertSee('placeholder="Tìm kiếm sản phẩm, thương hiệu, danh mục…"', false)
            ->assertSeeText('Tất cả danh mục')
            ->assertSeeText('GIẢM ĐẾN')
            ->assertSeeText('60%')
            ->assertSeeText('CHO TẤT CẢ SẢN PHẨM');
    }

    public function test_home_renders_english_locale_and_falls_back_to_vietnamese_fields(): void
    {
        $category = $this->category('english-category');
        $product = $this->product($category, 'english-product', [
            'name_vi' => 'Tên dự phòng',
            'name_en' => 'English product',
            'description_en' => 'English description',
            'is_featured' => true,
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/english.webp',
            'is_primary' => true,
        ]);

        $this->withSession(['storefront_locale' => 'en'])
            ->get(route('home'))
            ->assertOk()
            ->assertSeeText('Featured products')
            ->assertSeeText('English product')
            ->assertSeeText('English description')
            ->assertSeeText('Shop by category')
            ->assertSee('placeholder="Search for products, brands, categories…"', false)
            ->assertSeeText('All Categories')
            ->assertSeeText('UP TO')
            ->assertSeeText('60% OFF')
            ->assertSeeText('ALL ITEMS')
            ->assertSeeText('Get the latest from our store');
    }

    public function test_home_has_a_safe_empty_catalog_state_and_real_newsletter_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSeeText('Danh mục sản phẩm đang được cập nhật')
            ->assertSee(route('shop'), false)
            ->assertDontSee('product-card', false)
            ->assertDontSee('product.html', false)
            ->assertSee('data-newsletter-form', false)
            ->assertSeeText('Nhận tin mới từ cửa hàng');
    }

    public function test_trending_endpoint_returns_published_category_cards_with_stable_limit(): void
    {
        $category = $this->category('ajax-category');
        $otherCategory = $this->category('other-category');

        foreach (range(1, 7) as $position) {
            $this->product($category, 'ajax-'.$position, [
                'quantity' => $position === 7 ? 0 : 3,
                'is_featured' => $position === 6,
                'created_at' => now()->subMinutes($position),
            ]);
        }
        $this->product($category, 'ajax-hidden', ['status' => false]);
        $this->product($otherCategory, 'other-product');

        $response = $this->getJson(route('home.trending', ['category' => $category->slug]));

        $response->assertOk()
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('category.slug', 'ajax-category')
            ->assertJsonStructure(['html', 'category' => ['id', 'slug']]);

        $html = (string) $response->json('html');
        $this->assertSame(5, substr_count($html, '<article class="product-card">'));
        $this->assertStringContainsString('Sản phẩm ajax-6', $html);
        $this->assertStringNotContainsString('ajax-hidden', $html);
        $this->assertStringNotContainsString('other-product', $html);
        $this->assertStringNotContainsString('ajax-7', $html);
    }

    public function test_trending_endpoint_rejects_inactive_unknown_and_empty_categories(): void
    {
        $inactive = $this->category('inactive-category', false);
        $this->product($inactive, 'inactive-category-product');
        $empty = $this->category('empty-category');

        $this->getJson(route('home.trending', ['category' => $inactive->slug]))->assertNotFound();
        $this->getJson(route('home.trending', ['category' => $empty->slug]))->assertNotFound();
        $this->getJson(route('home.trending', ['category' => 'missing-category']))->assertNotFound();
    }

    public function test_home_trending_tabs_are_accessible_and_keep_shop_fallback_links(): void
    {
        $first = $this->category('first-tab');
        $second = $this->category('second-tab');
        $this->product($first, 'first-product', ['quantity' => 2]);
        $this->product($second, 'second-product', ['quantity' => 2]);

        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('data-trending-tabs', false)
            ->assertSee('role="tablist"', false)
            ->assertSee('role="tabpanel"', false)
            ->assertSee('aria-selected="true"', false)
            ->assertSee('aria-selected="false"', false)
            ->assertSee(route('shop', ['category' => 'first-tab']), false)
            ->assertSee(route('shop', ['category' => 'second-tab']), false)
            ->assertSee(route('home.trending', ['category' => 'second-tab']), false)
            ->assertSeeText('Sản phẩm first-product')
            ->assertSeeText('Đang tải sản phẩm…')
            ->assertSeeText('Không thể tải sản phẩm. Vui lòng thử lại.');
    }

    public function test_trending_endpoint_stays_within_query_budget(): void
    {
        $category = $this->category('query-tab');
        foreach (range(1, 5) as $position) {
            $this->product($category, 'query-'.$position);
        }

        $this->getJson(route('home.trending', ['category' => $category->slug]))->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson(route('home.trending', ['category' => $category->slug]))->assertOk();

        $this->assertLessThanOrEqual(8, count(DB::getQueryLog()));
    }

    private function category(string $slug = 'category', bool $status = true): Category
    {
        return Category::create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    private function brand(string $slug): Brand
    {
        return Brand::create([
            'name_vi' => 'ThÆ°Æ¡ng hiá»‡u '.$slug,
            'name_en' => 'Brand '.$slug,
            'slug' => $slug,
            'status' => true,
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
            'quantity' => 1,
            'is_featured' => false,
            'status' => true,
        ], $overrides));
    }
}
