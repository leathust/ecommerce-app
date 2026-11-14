<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use App\Services\CartIdentityService;
use App\Services\CheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_cod_checkout_uses_server_totals_and_keeps_stock_until_confirmation(): void
    {
        $user=User::factory()->create(); $product=Product::factory()->create(['price'=>100000,'quantity'=>5]);
        $cart=Cart::factory()->forCustomer($user)->create(); CartItem::factory()->for($cart)->for($product)->create(['quantity'=>2]);
        $response=$this->actingAs($user)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$this->payload('cod'));
        $response->assertCreated()->assertJson(['created'=>true,'payment_status'=>'unpaid']);
        $this->assertDatabaseHas('orders',['user_id'=>$user->id,'subtotal'=>'200000.00','payment_method'=>'cod']);
        $this->assertSame(5,$product->fresh()->quantity); $this->assertDatabaseCount('cart_items',0); $this->assertDatabaseCount('payment_attempts',0);
    }

    public function test_sepay_checkout_creates_attempt_and_reserves_stock_atomically(): void
    {
        config(['sepay.enabled'=>true,'sepay.environment'=>'sandbox','sepay.environments.sandbox.bank_code'=>'VCB',
            'sepay.environments.sandbox.account_number'=>'123456','sepay.environments.sandbox.account_name'=>'SHOP']);
        $user=User::factory()->create(); $product=Product::factory()->create(['price'=>150000,'quantity'=>4]);
        $cart=Cart::factory()->forCustomer($user)->create(); CartItem::factory()->for($cart)->for($product)->create(['quantity'=>2]);
        $response=$this->actingAs($user)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$this->payload('sepay'));
        $response->assertCreated()->assertJsonPath('created',true)->assertJsonStructure(['payment_attempt']);
        $this->assertDatabaseHas('payment_attempts',['expected_amount'=>300000,'environment'=>'sandbox','status'=>'pending']);
        $this->assertSame(2,$product->fresh()->quantity); $this->assertNotNull($user->orders()->first()->stock_deducted_at);
    }

    public function test_duplicate_idempotency_key_returns_existing_order_without_second_stock_deduction(): void
    {
        $user=User::factory()->create(); $product=Product::factory()->create(['price'=>100000,'quantity'=>5]);
        $cart=Cart::factory()->forCustomer($user)->create(); CartItem::factory()->for($cart)->for($product)->create(['quantity'=>1]);
        $payload=$this->payload('cod');
        $this->actingAs($user)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$payload)->assertCreated();
        $this->actingAs($user)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$payload)->assertOk()->assertJson(['created'=>false]);
        $this->assertDatabaseCount('orders',1); $this->assertSame(5,$product->fresh()->quantity);
    }

    public function test_guest_is_cookie_scoped_and_failure_keeps_cart_intact(): void
    {
        $raw=rtrim(strtr(base64_encode(random_bytes(32)),'+/','-_'),'=');
        $cart=Cart::factory()->guestWithToken($raw)->create(); $product=Product::factory()->outOfStock()->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity'=>1]);
        $this->withCookie(CartIdentityService::COOKIE_NAME,$raw)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$this->payload('cod'))->assertUnprocessable();
        $this->assertDatabaseCount('orders',0); $this->assertDatabaseHas('cart_items',['cart_id'=>$cart->id,'product_id'=>$product->id]);
    }

    public function test_expired_sepay_attempt_restores_reserved_stock_exactly_once(): void
    {
        config(['sepay.enabled'=>true,'sepay.environment'=>'sandbox','sepay.environments.sandbox.bank_code'=>'VCB',
            'sepay.environments.sandbox.account_number'=>'123456','sepay.environments.sandbox.account_name'=>'SHOP']);
        $user=User::factory()->create(); $product=Product::factory()->create(['price'=>100000,'quantity'=>3]);
        $cart=Cart::factory()->forCustomer($user)->create(); CartItem::factory()->for($cart)->for($product)->create(['quantity'=>2]);
        $this->actingAs($user)->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])->postJson(route('checkout.store'),$this->payload('sepay'))->assertCreated();
        $attempt=$user->orders()->first()->paymentAttempts()->first(); $attempt->update(['expires_at'=>now()->subSecond()]);
        $service=app(CheckoutService::class);
        $this->assertTrue($service->expire($attempt)); $this->assertFalse($service->expire($attempt));
        $this->assertSame(3,$product->fresh()->quantity); $this->assertSame('expired',$attempt->fresh()->status);
        $this->assertSame('cancelled',$attempt->order->fresh()->status);
    }

    private function payload(string $method): array
    { return ['recipient_name'=>'Test User','recipient_phone'=>'0901234567','shipping_address'=>'123 Test Street','note'=>null,
        'payment_method'=>$method,'idempotency_key'=>$this->key()]; }

    private function key(): string
    { return hash('sha256','checkout-test-key'); }
}
