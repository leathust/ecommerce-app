<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Wishlist;
use App\Services\WishlistService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class WishlistMutationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_customer_can_add_product_and_repeated_add_is_idempotent(): void
    {
        $customer = User::factory()->create();
        $product = $this->product();
        $url = route('wishlist.store', ['storefrontProduct' => $product->slug]);

        $this->actingAs($customer)
            ->postJson($url)
            ->assertCreated()
            ->assertJson([
                'is_wishlisted' => true,
                'wishlist_count' => 1,
            ])
            ->assertJsonStructure(['message']);

        $this->actingAs($customer)
            ->postJson($url)
            ->assertOk()
            ->assertJson([
                'is_wishlisted' => true,
                'wishlist_count' => 1,
            ]);

        $this->assertDatabaseCount('wishlists', 1);
        $this->assertDatabaseHas('wishlists', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_add_uses_authenticated_owner_and_html_fallback_accepts_only_safe_return_url(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product();
        $url = route('wishlist.store', ['storefrontProduct' => $product->slug]);

        $this->actingAs($customer)
            ->from(route('products.show', $product->slug))
            ->post($url, [
                'user_id' => $other->id,
                'return_to' => '/shop?page=2',
            ])
            ->assertRedirect('/shop?page=2')
            ->assertSessionHas('wishlist_success');

        $this->assertDatabaseHas('wishlists', [
            'user_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        $this->assertDatabaseMissing('wishlists', ['user_id' => $other->id]);

        $secondProduct = $this->product('safe-fallback');
        $this->actingAs($customer)
            ->withHeader('referer', 'https://evil.example/previous')
            ->post(route('wishlist.store', ['storefrontProduct' => $secondProduct->slug]), [
                'return_to' => 'https://evil.example/steal',
            ])
            ->assertRedirect(route('shop'));
    }

    public function test_customer_can_remove_only_their_item_and_repeated_remove_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = $this->product();
        Wishlist::query()->create(['user_id' => $owner->id, 'product_id' => $product->id]);
        Wishlist::query()->create(['user_id' => $other->id, 'product_id' => $product->id]);

        $url = route('wishlist.destroy', $product);

        $this->actingAs($owner)
            ->deleteJson($url)
            ->assertOk()
            ->assertJson([
                'is_wishlisted' => false,
                'wishlist_count' => 0,
            ]);

        $this->actingAs($owner)
            ->deleteJson($url)
            ->assertOk()
            ->assertJson([
                'is_wishlisted' => false,
                'wishlist_count' => 0,
            ]);

        $this->assertDatabaseMissing('wishlists', ['user_id' => $owner->id, 'product_id' => $product->id]);
        $this->assertDatabaseHas('wishlists', ['user_id' => $other->id, 'product_id' => $product->id]);
    }

    public function test_inactive_product_cannot_be_added_but_existing_item_can_be_removed(): void
    {
        $customer = User::factory()->create();
        $inactive = $this->product('inactive-product', ['status' => false]);

        $this->actingAs($customer)
            ->postJson(route('wishlist.store', ['storefrontProduct' => $inactive->slug]))
            ->assertNotFound();

        Wishlist::query()->create(['user_id' => $customer->id, 'product_id' => $inactive->id]);

        $this->actingAs($customer)
            ->deleteJson(route('wishlist.destroy', $inactive))
            ->assertOk()
            ->assertJsonPath('is_wishlisted', false);

        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_product_in_inactive_category_and_unknown_products_are_rejected(): void
    {
        $customer = User::factory()->create();
        $category = $this->category('hidden-category', false);
        $product = $this->product('hidden-category-product', [], $category);

        $this->actingAs($customer)
            ->postJson(route('wishlist.store', ['storefrontProduct' => $product->slug]))
            ->assertNotFound();

        $this->actingAs($customer)
            ->postJson(route('wishlist.store', ['storefrontProduct' => 'unknown-product']))
            ->assertNotFound();

        $this->actingAs($customer)
            ->deleteJson('/wishlist/999999')
            ->assertNotFound();
    }

    public function test_guest_admin_and_inactive_customer_cannot_mutate_wishlist(): void
    {
        $product = $this->product();
        $storeUrl = route('wishlist.store', ['storefrontProduct' => $product->slug]);

        $this->post($storeUrl)->assertRedirect(route('login'));
        $this->postJson($storeUrl)->assertUnauthorized();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->postJson($storeUrl)->assertForbidden();

        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($inactive)->postJson($storeUrl)->assertForbidden();
        $this->assertGuest();
        $this->assertDatabaseCount('wishlists', 0);
    }

    public function test_mutation_response_is_localized_and_does_not_expose_user_data(): void
    {
        $customer = User::factory()->create();
        $product = $this->product();

        $response = $this->actingAs($customer)
            ->withSession(['storefront_locale' => 'en'])
            ->postJson(route('wishlist.store', ['storefrontProduct' => $product->slug]));

        $response->assertCreated()
            ->assertJsonPath('message', 'The product was added to your wishlist.')
            ->assertJsonMissing(['user_id' => $customer->id])
            ->assertJsonMissingPath('email');
    }

    public function test_service_count_and_model_scope_are_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->product('first');
        $second = $this->product('second');
        Wishlist::query()->create(['user_id' => $owner->id, 'product_id' => $first->id]);
        Wishlist::query()->create(['user_id' => $owner->id, 'product_id' => $second->id]);
        Wishlist::query()->create(['user_id' => $other->id, 'product_id' => $first->id]);

        $this->assertSame(2, app(WishlistService::class)->count($owner));
        $this->assertSame(1, Wishlist::query()->forUser($other)->count());
    }

    public function test_routes_use_published_binding_customer_boundary_throttle_and_web_csrf_group(): void
    {
        $store = Route::getRoutes()->getByName('wishlist.store');
        $destroy = Route::getRoutes()->getByName('wishlist.destroy');

        $this->assertSame(['POST'], $store->methods());
        $this->assertSame(['DELETE'], $destroy->methods());

        foreach ([$store, $destroy] as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('storefront.locale', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('customer', $middleware);
            $this->assertContains('throttle:wishlist-mutations', $middleware);
        }
    }

    public function test_wishlist_mutations_are_rate_limited_per_authenticated_customer(): void
    {
        $customer = User::factory()->create();
        $product = $this->product('throttled-product');
        $url = route('wishlist.store', ['storefrontProduct' => $product->slug]);
        RateLimiter::clear(md5('wishlist-mutations'.$customer->getAuthIdentifier()));

        foreach (range(1, 60) as $attempt) {
            $this->actingAs($customer)->postJson($url)->assertSuccessful();
        }

        $this->actingAs($customer)->postJson($url)->assertTooManyRequests();
    }

    private function category(string $slug = 'wishlist-category', bool $status = true): Category
    {
        return Category::query()->create([
            'name_vi' => 'Danh mục '.$slug,
            'name_en' => 'Category '.$slug,
            'slug' => $slug,
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function product(string $slug = 'wishlist-product', array $overrides = [], ?Category $category = null): Product
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
