<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use App\Models\Wishlist;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WishlistPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_sees_only_their_published_wishlist_with_shared_cards_and_remove_forms(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $visible = $this->product('visible-product');
        $otherProduct = $this->product('other-product');
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $visible->id]);
        Wishlist::query()->create(['user_id' => $other->id, 'product_id' => $otherProduct->id]);

        $this->actingAs($customer)
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertViewIs('frontend.wishlist.index')
            ->assertViewHas('products', fn ($products) => $products->total() === 1)
            ->assertSeeText('Sản phẩm visible-product')
            ->assertDontSeeText('Sản phẩm other-product')
            ->assertSee('class="product-card"', false)
            ->assertSee(route('wishlist.destroy', $visible), false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_wishlist_is_paginated_newest_first_and_keeps_complete_page_links(): void
    {
        $customer = User::factory()->create();

        foreach (range(1, 13) as $number) {
            $product = $this->product('page-product-'.$number);
            Wishlist::query()->create([
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'created_at' => now()->addSeconds($number),
            ]);
        }

        $firstPage = $this->actingAs($customer)->get(route('wishlist.index'));
        $firstPage->assertOk()
            ->assertViewHas('products', fn ($products) => $products->total() === 13 && $products->perPage() === 12)
            ->assertViewHas('products', fn ($products) => $products->getCollection()->pluck('slug')->doesntContain('page-product-1'))
            ->assertSeeText('Sản phẩm page-product-13')
            ->assertSee(route('wishlist.index', ['page' => 2]), false);

        $this->actingAs($customer)
            ->get(route('wishlist.index', ['page' => 2]))
            ->assertOk()
            ->assertSeeText('Sản phẩm page-product-1')
            ->assertDontSeeText('Sản phẩm page-product-13');
    }

    public function test_inactive_product_or_category_is_hidden_without_deleting_wishlist_rows(): void
    {
        $customer = User::factory()->create();
        $inactiveProduct = $this->product('inactive-product', ['status' => false]);
        $inactiveCategory = $this->category('inactive-category', false);
        $hiddenByCategory = $this->product('hidden-by-category', [], $inactiveCategory);
        $visible = $this->product('still-visible');

        foreach ([$inactiveProduct, $hiddenByCategory, $visible] as $product) {
            Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
        }

        $this->actingAs($customer)
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertSeeText('Sản phẩm still-visible')
            ->assertDontSeeText('Sản phẩm inactive-product')
            ->assertDontSeeText('Sản phẩm hidden-by-category')
            ->assertViewHas('products', fn ($products) => $products->total() === 1);

        $this->assertDatabaseCount('wishlists', 3);
    }

    public function test_empty_state_and_english_locale_are_rendered(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertSeeText('Your wishlist is empty')
            ->assertSeeText('Explore products')
            ->assertSee(route('shop'), false)
            ->assertSee('noindex,nofollow', false);
    }

    public function test_page_uses_localized_presenter_media_price_stock_and_brand_eager_load(): void
    {
        $customer = User::factory()->create();
        $brand = Brand::query()->create([
            'name_vi' => 'Thương hiệu thử nghiệm',
            'name_en' => 'Test brand',
            'slug' => 'test-brand',
            'status' => true,
        ]);
        $product = $this->product('localized-product', [
            'brand_id' => $brand->id,
            'name_en' => 'English wishlist product',
            'price' => 125000,
            'quantity' => 4,
        ]);
        ProductImage::query()->create([
            'product_id' => $product->id,
            'image_url' => 'https://cdn.example.com/wishlist.webp',
            'alt_text' => 'Wishlist product image',
            'is_primary' => true,
        ]);
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);

        $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('wishlist.index'))
            ->assertOk()
            ->assertSeeText('English wishlist product')
            ->assertSee('https://cdn.example.com/wishlist.webp', false)
            ->assertSeeText('4 items')
            ->assertSeeText('125.000 ₫');
    }

    public function test_removing_the_last_item_on_page_moves_back_to_last_valid_page(): void
    {
        $customer = User::factory()->create();
        $products = collect(range(1, 13))->map(function (int $number) use ($customer): Product {
            $product = $this->product('delete-page-'.$number);
            Wishlist::query()->create([
                'user_id' => $customer->id,
                'product_id' => $product->id,
                'created_at' => now()->addSeconds($number),
            ]);

            return $product;
        });
        $lastItem = $products->first();

        $this->actingAs($customer)
            ->delete(route('wishlist.destroy', $lastItem), [
                'return_to' => route('wishlist.index', ['page' => 2]),
            ])
            ->assertRedirect(route('wishlist.index'))
            ->assertSessionHas('wishlist_success');

        $this->assertDatabaseCount('wishlists', 12);
    }

    public function test_page_is_protected_by_customer_boundary(): void
    {
        $this->get(route('wishlist.index'))->assertRedirect(route('login'));

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->get(route('wishlist.index'))->assertForbidden();

        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($inactive)->get(route('wishlist.index'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_page_query_count_does_not_grow_with_number_of_items(): void
    {
        $customer = User::factory()->create();
        foreach (range(1, 8) as $number) {
            $product = $this->product('query-product-'.$number);
            Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($customer)->get(route('wishlist.index'))->assertOk();
        $catalogQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains($query['query'], 'products')
                || str_contains($query['query'], 'product_images')
                || str_contains($query['query'], 'categories')
                || str_contains($query['query'], 'brands'),
        );

        $this->assertLessThanOrEqual(6, $catalogQueries->count());
    }

    public function test_route_uses_locale_auth_and_customer_middleware_without_mutation_throttle(): void
    {
        $route = Route::getRoutes()->getByName('wishlist.index');
        $middleware = $route->gatherMiddleware();

        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('web', $middleware);
        $this->assertContains('storefront.locale', $middleware);
        $this->assertContains('auth', $middleware);
        $this->assertContains('customer', $middleware);
        $this->assertNotContains('throttle:wishlist-mutations', $middleware);
    }

    private function category(string $slug, bool $status = true): Category
    {
        return Category::query()->create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(string $slug, array $overrides = [], ?Category $category = null): Product
    {
        $category ??= $this->category('category-'.$slug);

        return Product::query()->create(array_merge([
            'category_id' => $category->id,
            'sku' => 'SKU-'.strtoupper($slug),
            'name_vi' => 'Sản phẩm '.$slug,
            'name_en' => 'Product '.$slug,
            'slug' => $slug,
            'price' => 100000,
            'quantity' => 10,
            'status' => true,
        ], $overrides));
    }
}
