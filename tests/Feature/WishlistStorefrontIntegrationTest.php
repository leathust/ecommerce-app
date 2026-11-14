<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WishlistStorefrontIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_uses_real_wishlist_route_and_published_count(): void
    {
        $customer = User::factory()->create();
        $first = $this->product('header-first');
        $second = $this->product('header-second');
        $inactive = $this->product('header-inactive', ['status' => false]);
        foreach ([$first, $second, $inactive] as $product) {
            Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
        }

        $this->actingAs($customer)
            ->get(route('home'))
            ->assertOk()
            ->assertSee('href="'.route('wishlist.index').'"', false)
            ->assertSee('<span class="count" data-wishlist-count>2</span>', false)
            ->assertDontSee('<span class="count" data-wishlist-count>3</span>', false);
    }

    public function test_guest_header_has_no_fake_count_and_cards_offer_login_cta(): void
    {
        $product = $this->product('guest-card');

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('href="'.route('wishlist.index').'"', false)
            ->assertDontSee('data-wishlist-count', false)
            ->assertSee('wishlist-toggle--card', false)
            ->assertSee(route('login'), false)
            ->assertSeeText('Đăng nhập để lưu sản phẩm')
            ->assertDontSee(route('wishlist.store', ['storefrontProduct' => $product->slug]), false);
    }

    public function test_product_cards_render_server_side_active_state_and_no_js_forms(): void
    {
        $customer = User::factory()->create();
        $saved = $this->product('saved-card');
        $unsaved = $this->product('unsaved-card');
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $saved->id]);

        $this->actingAs($customer)
            ->get(route('shop'))
            ->assertOk()
            ->assertSee(route('wishlist.destroy', $saved), false)
            ->assertSee(route('wishlist.store', ['storefrontProduct' => $unsaved->slug]), false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('name="_method" value="DELETE"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="return_to"', false);
    }

    public function test_product_detail_and_related_cards_use_the_same_wishlist_control(): void
    {
        $customer = User::factory()->create();
        $category = $this->category('detail-category');
        $current = $this->product('detail-current', [], $category);
        $related = $this->product('detail-related', [], $category);
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $current->id]);

        $this->actingAs($customer)
            ->get(route('products.show', $current->slug))
            ->assertOk()
            ->assertSee('wishlist-toggle-form--detail', false)
            ->assertSeeText('Bỏ khỏi danh sách')
            ->assertSee(route('wishlist.destroy', $current), false)
            ->assertSee(route('wishlist.store', ['storefrontProduct' => $related->slug]), false);
    }

    public function test_trending_ajax_partial_contains_customer_wishlist_state(): void
    {
        $customer = User::factory()->create();
        $category = $this->category('trending-category');
        $product = $this->product('trending-saved', ['is_featured' => true], $category);
        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);

        $response = $this->actingAs($customer)->getJson(route('home.trending', $category->slug));

        $response->assertOk()->assertJsonStructure(['html']);
        $html = (string) $response->json('html');
        $this->assertStringContainsString(route('wishlist.destroy', $product), $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
    }

    public function test_wishlist_context_executes_one_wishlist_query_for_many_cards(): void
    {
        $customer = User::factory()->create();
        foreach (range(1, 8) as $number) {
            $product = $this->product('query-card-'.$number);
            if ($number <= 4) {
                Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $product->id]);
            }
        }

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'wishlists')) {
                $queries[] = $query->sql;
            }
        });

        $this->actingAs($customer)->get(route('shop'))->assertOk();

        $this->assertCount(1, $queries);
    }

    public function test_wishlist_context_does_not_leak_between_customers(): void
    {
        $firstCustomer = User::factory()->create();
        $secondCustomer = User::factory()->create();
        $product = $this->product('context-isolation');
        Wishlist::query()->create(['user_id' => $firstCustomer->id, 'product_id' => $product->id]);

        $this->actingAs($firstCustomer)
            ->get(route('shop'))
            ->assertSee(route('wishlist.destroy', $product), false);

        auth()->logout();

        $this->actingAs($secondCustomer)
            ->get(route('shop'))
            ->assertSee(route('wishlist.store', ['storefrontProduct' => $product->slug]), false)
            ->assertSee('aria-pressed="false"', false)
            ->assertDontSee('class="wishlist-toggle is-active"', false);
    }

    private function category(string $slug): Category
    {
        return Category::query()->create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => $slug,
            'status' => true,
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
