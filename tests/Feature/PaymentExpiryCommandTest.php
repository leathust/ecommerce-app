<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentExpiryCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_expires_due_attempt_and_restores_stock_exactly_once(): void
    {
        [$order, $attempt, $product] = $this->sepayCheckout();
        $attempt->update(['expires_at' => now()->subMinute()]);

        $this->artisan('sepay:expire-payments')
            ->expectsOutputToContain('Scanned 1, expired 1.')
            ->assertSuccessful();

        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->stock_restored_at);
        $this->assertSame(5, $product->fresh()->quantity);

        $this->artisan('sepay:expire-payments')->assertSuccessful();

        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertSame(0, PaymentAttempt::query()->where('status', 'pending')->count());
    }

    public function test_command_ignores_future_attempts_and_paid_attempts(): void
    {
        $futureOrder = Order::factory()->create(['payment_method' => 'bank_transfer', 'payment_status' => 'unpaid']);
        $future = PaymentAttempt::factory()->for($futureOrder)->create([
            'status' => 'pending', 'expires_at' => now()->addMinutes(10),
        ]);

        $paidOrder = Order::factory()->create(['payment_method' => 'bank_transfer', 'payment_status' => 'paid', 'paid_at' => now()]);
        $paid = PaymentAttempt::factory()->for($paidOrder)->create([
            'status' => 'paid', 'expires_at' => now()->subMinute(), 'paid_at' => now(),
        ]);

        $this->artisan('sepay:expire-payments')->assertSuccessful();

        $this->assertSame('pending', $future->fresh()->status);
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('pending', $futureOrder->fresh()->status);
        $this->assertSame('paid', $paidOrder->fresh()->payment_status);
    }

    /** @return array{0: Order, 1: PaymentAttempt, 2: Product} */
    private function sepayCheckout(): array
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.bank_code' => 'VCB',
            'sepay.environments.sandbox.account_number' => '123456789',
            'sepay.environments.sandbox.account_name' => 'SHOP TEST',
        ]);

        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 5]);
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $this->actingAs($user)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => hash('sha256', 'expiry-command-test')])
            ->postJson(route('checkout.store'), [
                'recipient_name' => 'Test User',
                'recipient_phone' => '0901234567',
                'shipping_address' => '123 Test Street',
                'note' => null,
                'payment_method' => 'sepay',
                'idempotency_key' => hash('sha256', 'expiry-command-test'),
            ])
            ->assertCreated();

        $attempt = PaymentAttempt::query()->firstOrFail();

        return [$attempt->order()->firstOrFail(), $attempt, $product];
    }
}
