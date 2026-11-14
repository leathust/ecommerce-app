<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CartSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cart_and_item_factories_create_valid_relations(): void
    {
        $item = CartItem::factory()->create();
        $cart = $item->cart;

        $this->assertTrue($cart->isGuest());
        $this->assertFalse($cart->isCustomerOwned());
        $this->assertNull($cart->user_id);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cart->session_token);
        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertSame($item->id, $cart->items()->firstOrFail()->id);
        $this->assertIsInt($item->quantity);
    }

    public function test_customer_cart_factory_and_scopes_follow_owner_contract(): void
    {
        $customer = User::factory()->create();
        $customerCart = Cart::factory()->forCustomer($customer)->create();
        $guestCart = Cart::factory()->guest()->create();

        $this->assertTrue($customerCart->isCustomerOwned());
        $this->assertFalse($customerCart->isGuest());
        $this->assertNull($customerCart->session_token);
        $this->assertSame($customerCart->id, Cart::query()->forUser($customer)->firstOrFail()->id);
        $this->assertSame($guestCart->id, Cart::query()->forSessionHash($guestCart->session_token)->firstOrFail()->id);
    }

    public function test_database_allows_only_one_cart_per_customer_but_multiple_guests(): void
    {
        $customer = User::factory()->create();

        Cart::factory()->forCustomer($customer)->create();
        Cart::factory()->count(2)->guest()->create();

        try {
            Cart::factory()->forCustomer($customer)->create();
            $this->fail('Unique customer Cart invariant was not enforced.');
        } catch (QueryException) {
            $this->assertSame(1, Cart::query()->forUser($customer)->count());
            $this->assertSame(2, Cart::query()->whereNull('user_id')->count());
        }
    }

    public function test_additive_migration_merges_legacy_duplicate_customer_carts_without_dropping_items(): void
    {
        $migration = require database_path('migrations/2026_11_14_000010_enforce_unique_customer_carts.php');
        $migration->down();

        $customer = User::factory()->create();
        $limitedProduct = Product::factory()->create(['quantity' => 5]);
        $unavailableProduct = Product::factory()->outOfStock()->create();
        $older = now()->subDay();
        $newer = now();

        $olderCartId = DB::table('carts')->insertGetId([
            'user_id' => $customer->id,
            'session_token' => hash('sha256', 'legacy-customer-token'),
            'created_at' => $older,
            'updated_at' => $older,
        ]);
        $newerCartId = DB::table('carts')->insertGetId([
            'user_id' => $customer->id,
            'session_token' => null,
            'created_at' => $newer,
            'updated_at' => $newer,
        ]);

        DB::table('cart_items')->insert([
            ['cart_id' => $olderCartId, 'product_id' => $limitedProduct->id, 'quantity' => 4, 'created_at' => $older, 'updated_at' => $older],
            ['cart_id' => $newerCartId, 'product_id' => $limitedProduct->id, 'quantity' => 3, 'created_at' => $newer, 'updated_at' => $newer],
            ['cart_id' => $olderCartId, 'product_id' => $unavailableProduct->id, 'quantity' => 2, 'created_at' => $older, 'updated_at' => $older],
        ]);

        $migration->up();

        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseHas('carts', [
            'id' => $newerCartId,
            'user_id' => $customer->id,
            'session_token' => null,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $newerCartId,
            'product_id' => $limitedProduct->id,
            'quantity' => 5,
        ]);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $newerCartId,
            'product_id' => $unavailableProduct->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseCount('cart_items', 2);
    }
}
