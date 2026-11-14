<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_token_helper_hashes_raw_token_and_never_persists_it(): void
    {
        $rawToken = str_repeat('f', 43);
        $cart = Cart::factory()->guestWithToken($rawToken)->create();

        $this->assertTrue($cart->isGuest());
        $this->assertSame(hash('sha256', $rawToken), $cart->session_token);
        $this->assertNotSame($rawToken, $cart->session_token);
    }

    public function test_customer_cart_helper_creates_requested_valid_items(): void
    {
        $customer = User::factory()->create();
        $cart = Cart::factory()->forCustomer($customer)->withItems(3)->create();

        $this->assertTrue($cart->isCustomerOwned());
        $this->assertNull($cart->session_token);
        $this->assertCount(3, $cart->items);
        $this->assertTrue($cart->items->every(fn (CartItem $item): bool => $item->quantity >= 1));
    }

    public function test_unavailable_and_inactive_item_states_are_explicit(): void
    {
        $cart = Cart::factory()->withUnavailableItem(4)->create();
        $inactive = CartItem::factory()->for($cart)->inactive()->quantity(2)->create();

        $unavailable = $cart->items()->whereKeyNot($inactive->id)->firstOrFail();
        $this->assertSame(4, $unavailable->quantity);
        $this->assertSame(0, $unavailable->product->quantity);
        $this->assertFalse((bool) $inactive->product->status);
        $this->assertSame(2, $inactive->quantity);
    }

    public function test_cart_delete_cascades_items_but_product_with_cart_history_is_restricted(): void
    {
        $item = CartItem::factory()->create();
        $cartId = $item->cart_id;
        $product = $item->product;

        try {
            $product->delete();
            $this->fail('Product referenced by CartItem must be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('cart_items', ['id' => $item->id]);
        }

        Cart::query()->findOrFail($cartId)->delete();
        $this->assertDatabaseMissing('cart_items', ['id' => $item->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_default_database_seeder_does_not_seed_personal_cart_data(): void
    {
        $seeder = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertStringNotContainsString('CartSeeder::class', $seeder);
        $this->assertFileDoesNotExist(database_path('seeders/CartSeeder.php'));
    }

    public function test_operations_runbook_keeps_safe_commands_and_token_redaction_rules(): void
    {
        $runbook = file_get_contents(base_path('docs/cart-operations.md'));

        $this->assertStringContainsString('php artisan migrate:status', $runbook);
        $this->assertStringContainsString('php artisan migrate --pretend', $runbook);
        $this->assertStringContainsString('php artisan test --filter=Cart', $runbook);
        $this->assertStringContainsString('không có `CartSeeder`', $runbook);
        $this->assertStringContainsString('Không đưa cookie, token/hash', $runbook);
        $this->assertStringContainsString('Project hiện chưa có cleanup job', $runbook);
        $this->assertStringContainsString('Checkout', $runbook);
        $this->assertStringNotContainsString('migrate:fresh', $this->commandBlock($runbook));
        $this->assertStringNotContainsString('DB_PASSWORD=', $runbook);
    }

    private function commandBlock(string $runbook): string
    {
        preg_match_all('/```powershell\R(.*?)```/s', $runbook, $matches);

        return implode("\n", $matches[1] ?? []);
    }
}
