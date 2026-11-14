<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CartIdentityService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CartMutationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_can_add_product_and_repeated_add_uses_one_line(): void
    {
        $product = Product::factory()->create(['price' => 125000, 'quantity' => 10]);
        $url = route('cart.items.store', ['storefrontProduct' => $product->slug]);

        $first = $this->postJson($url, ['quantity' => 2])
            ->assertCreated()
            ->assertJson([
                'item_quantity' => 2,
                'cart_count' => 2,
                'line_count' => 1,
                'subtotal' => '250000.00',
                'product_id' => $product->id,
            ]);
        $rawToken = $first->getCookie(CartIdentityService::COOKIE_NAME)->getValue();

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->postJson($url, ['quantity' => 3])
            ->assertOk()
            ->assertJson([
                'item_quantity' => 5,
                'cart_count' => 5,
                'line_count' => 1,
                'subtotal' => '625000.00',
            ]);

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_customer_add_is_owner_scoped_and_does_not_trust_client_fields(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 10]);

        $this->actingAs($customer)->postJson(
            route('cart.items.store', ['storefrontProduct' => $product->slug]),
            [
                'quantity' => 2,
                'user_id' => $other->id,
                'cart_id' => 999999,
                'price' => 1,
                'subtotal' => 1,
            ],
        )->assertCreated()->assertJsonPath('subtotal', '200000.00');

        $cart = Cart::query()->forUser($customer)->firstOrFail();
        $this->assertNull($cart->session_token);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseMissing('carts', ['user_id' => $other->id]);
    }

    public function test_add_and_update_validate_integer_limit_and_current_stock(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 4]);
        $storeUrl = route('cart.items.store', ['storefrontProduct' => $product->slug]);

        foreach ([0, -1, 1.5, 'invalid', 100] as $quantity) {
            $this->actingAs($customer)
                ->postJson($storeUrl, compact('quantity'))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('quantity');
        }

        $this->actingAs($customer)
            ->postJson($storeUrl, ['quantity' => 5])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->actingAs($customer)->postJson($storeUrl, ['quantity' => 2])->assertCreated();
        $product->update(['quantity' => 1]);

        $this->actingAs($customer)
            ->patchJson(route('cart.items.update', $product), ['quantity' => 2])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_only_published_in_stock_product_can_be_added(): void
    {
        $inactive = Product::factory()->inactive()->create();
        $outOfStock = Product::factory()->outOfStock()->create();
        $hiddenCategory = Product::factory()->create();
        $hiddenCategory->category->update(['status' => false]);

        $this->postJson(route('cart.items.store', ['storefrontProduct' => $inactive->slug]))->assertNotFound();
        $this->postJson(route('cart.items.store', ['storefrontProduct' => $hiddenCategory->slug]))->assertNotFound();
        $this->postJson(route('cart.items.store', ['storefrontProduct' => $outOfStock->slug]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_owner_can_update_remove_and_clear_while_other_cart_is_untouched(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = Product::factory()->create(['price' => 10000, 'quantity' => 20]);
        $second = Product::factory()->create(['price' => 20000, 'quantity' => 20]);
        $ownerCart = Cart::factory()->forCustomer($owner)->create();
        $otherCart = Cart::factory()->forCustomer($other)->create();
        CartItem::factory()->for($ownerCart)->for($first)->create(['quantity' => 2]);
        CartItem::factory()->for($ownerCart)->for($second)->create(['quantity' => 1]);
        CartItem::factory()->for($otherCart)->for($first)->create(['quantity' => 7]);

        $this->actingAs($owner)
            ->patchJson(route('cart.items.update', $first), ['quantity' => 4])
            ->assertOk()
            ->assertJson(['item_quantity' => 4, 'cart_count' => 5, 'line_count' => 2, 'subtotal' => '60000.00']);

        $this->actingAs($owner)
            ->deleteJson(route('cart.items.destroy', $first))
            ->assertOk()
            ->assertJson(['removed' => true, 'cart_count' => 1, 'line_count' => 1]);

        $this->actingAs($owner)
            ->deleteJson(route('cart.items.destroy', $first))
            ->assertOk()
            ->assertJson(['removed' => false]);

        $this->actingAs($owner)
            ->deleteJson(route('cart.clear'))
            ->assertOk()
            ->assertJson(['removed_lines' => 1, 'cart_count' => 0, 'line_count' => 0, 'subtotal' => '0.00']);

        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $otherCart->id,
            'product_id' => $first->id,
            'quantity' => 7,
        ]);
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $ownerCart->id]);
    }

    public function test_foreign_or_missing_line_cannot_be_updated_and_unavailable_line_can_be_removed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();
        $ownerCart = Cart::factory()->forCustomer($owner)->create();
        Cart::factory()->forCustomer($other)->create();
        CartItem::factory()->for($ownerCart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($other)
            ->patchJson(route('cart.items.update', $product), ['quantity' => 1])
            ->assertNotFound();
        $this->actingAs($other)
            ->deleteJson(route('cart.items.destroy', $product))
            ->assertOk()
            ->assertJsonPath('removed', false);

        $product->update(['status' => false]);
        $this->actingAs($owner)
            ->patchJson(route('cart.items.update', $product), ['quantity' => 1])
            ->assertUnprocessable();
        $this->actingAs($owner)
            ->deleteJson(route('cart.items.destroy', $product))
            ->assertOk()
            ->assertJsonPath('removed', true);
    }

    public function test_admin_and_inactive_customer_cannot_create_or_mutate_cart(): void
    {
        $product = Product::factory()->create();
        $url = route('cart.items.store', ['storefrontProduct' => $product->slug]);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)->postJson($url)->assertForbidden();

        $inactive = User::factory()->create(['status' => 'inactive']);
        $this->actingAs($inactive)->postJson($url)->assertForbidden();

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_html_fallback_is_localized_and_rejects_external_return_url(): void
    {
        $product = Product::factory()->create();

        $this->withSession(['storefront_locale' => 'en'])
            ->post(route('cart.items.store', ['storefrontProduct' => $product->slug]), [
                'quantity' => 1,
                'return_to' => '/shop?page=2',
            ])
            ->assertRedirect('/shop?page=2')
            ->assertSessionHas('cart_success', 'The product was added to your cart.');

        $second = Product::factory()->create();
        $this->withHeader('referer', 'https://evil.example/previous')
            ->post(route('cart.items.store', ['storefrontProduct' => $second->slug]), [
                'return_to' => 'https://evil.example/steal',
            ])->assertRedirect(route('cart'));
    }

    public function test_json_contract_does_not_expose_owner_or_token(): void
    {
        $product = Product::factory()->create();
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)
            ->postJson(route('cart.items.store', ['storefrontProduct' => $product->slug]));

        $response->assertCreated()
            ->assertJsonStructure(['cart_count', 'line_count', 'subtotal', 'item_quantity', 'product_id', 'message'])
            ->assertJsonMissingPath('cart_id')
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('session_token');
    }

    public function test_routes_keep_binding_locale_csrf_and_named_throttle_contract(): void
    {
        $routes = collect([
            'cart.items.store' => ['POST'],
            'cart.items.update' => ['PATCH'],
            'cart.items.destroy' => ['DELETE'],
            'cart.clear' => ['DELETE'],
        ]);

        $routes->each(function (array $methods, string $name): void {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame($methods, $route->methods());
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('storefront.locale', $route->gatherMiddleware());
            $this->assertContains('throttle:cart-mutations', $route->gatherMiddleware());
            $this->assertNotContains('auth', $route->gatherMiddleware());
        });
    }

    public function test_guest_cart_mutations_are_rate_limited(): void
    {
        foreach (range(1, 60) as $attempt) {
            $this->deleteJson(route('cart.clear'))->assertOk();
        }

        $this->deleteJson(route('cart.clear'))->assertTooManyRequests();
    }
}
