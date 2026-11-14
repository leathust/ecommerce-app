<?php

namespace Tests\Feature;

use App\Http\Controllers\CheckoutController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\CartIdentityService;
use App\Services\PaymentAccessTokenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('vi');
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_checkout_page_renders_server_totals_and_a_session_scoped_key(): void
    {
        $raw = $this->rawCartToken();
        $cart = Cart::factory()->guestWithToken($raw)->create();
        $product = Product::factory()->create(['price' => 150000, 'quantity' => 10]);
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 2]);

        $response = $this->withCookie(CartIdentityService::COOKIE_NAME, $raw)->get(route('checkout'));

        $response->assertOk()
            ->assertSee(__('frontend.checkout.page_title'))
            ->assertSee('name="idempotency_key"', false)
            ->assertSee('name="recipient_name"', false)
            ->assertSee('name="payment_method"', false)
            ->assertSee('300.000', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) session(CheckoutController::IDEMPOTENCY_SESSION_KEY));
    }

    public function test_customer_checkout_page_prefills_account_name_and_phone(): void
    {
        $user = User::factory()->customerProfile()->create(['name' => 'Nguyen Van A', 'phone' => '0901234567']);
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 100000]))->create(['quantity' => 1]);

        $this->actingAs($user)->get(route('checkout'))
            ->assertOk()
            ->assertSee('Nguyen Van A')
            ->assertSee('0901234567');
    }

    public function test_admin_and_inactive_customer_cannot_open_checkout(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());

        $this->actingAs($admin)->get(route('checkout'))->assertForbidden();

        $inactive = User::factory()->inactiveCustomer()->create();
        $this->actingAs($inactive)->get(route('checkout'))->assertForbidden();
    }

    public function test_empty_cart_redirects_back_to_cart_with_an_error(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('checkout'))
            ->assertRedirect(route('cart'))
            ->assertSessionHasErrors('cart');
    }

    public function test_unavailable_item_blocks_submission_and_explains_why(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->outOfStock())->create(['quantity' => 1]);

        $this->actingAs($user)->get(route('checkout'))
            ->assertOk()
            ->assertSee(__('frontend.checkout.submit_blocked'))
            ->assertSee('disabled', false);
    }

    public function test_sepay_option_is_disabled_when_credentials_are_missing(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.bank_code' => null,
            'sepay.environments.sandbox.account_number' => null,
        ]);

        $user = User::factory()->create();
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 100000]))->create(['quantity' => 1]);

        $this->actingAs($user)->get(route('checkout'))
            ->assertOk()
            ->assertSee(__('frontend.checkout.sepay_unavailable'))
            ->assertDontSee('value="sepay"', false);
    }

    public function test_post_requires_the_idempotency_key_issued_by_the_checkout_page(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 100000]))->create(['quantity' => 1]);

        $this->actingAs($user)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])
            ->postJson(route('checkout.store'), $this->payload('cod', str_repeat('f', 64)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('idempotency_key');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_sepay_html_checkout_redirects_to_payment_page_and_sets_guest_access_cookie(): void
    {
        $this->enableSePay();
        $raw = $this->rawCartToken();
        $cart = Cart::factory()->guestWithToken($raw)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 150000, 'quantity' => 5]))->create(['quantity' => 2]);

        $response = $this->withCookie(CartIdentityService::COOKIE_NAME, $raw)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])
            ->post(route('checkout.store'), $this->payload('sepay'));

        $attempt = PaymentAttempt::query()->firstOrFail();
        $response->assertRedirect(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]));
        $response->assertCookie(PaymentAccessTokenService::COOKIE_NAME);

        $rawToken = (string) $response->getCookie(PaymentAccessTokenService::COOKIE_NAME)?->getValue();
        $this->assertNotSame('', $rawToken);
        $this->assertSame(hash('sha256', $rawToken), $attempt->fresh()->access_token_hash);
        $this->assertDatabaseHas('orders', ['id' => $attempt->order_id, 'payment_method' => 'bank_transfer']);
    }

    public function test_cod_html_checkout_keeps_existing_cart_redirect(): void
    {
        $user = User::factory()->create();
        $cart = Cart::factory()->forCustomer($user)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 100000]))->create(['quantity' => 1]);

        $this->actingAs($user)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key()])
            ->post(route('checkout.store'), $this->payload('cod'))
            ->assertRedirect(route('cart'))
            ->assertSessionHas('cart_success');

        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_a_consumed_key_is_rotated_on_the_next_checkout_page_view(): void
    {
        $oldKey = str_repeat('a', 64);
        $raw = $this->rawCartToken();
        $cart = Cart::factory()->guestWithToken($raw)->create();
        CartItem::factory()->for($cart)->for(Product::factory()->create(['price' => 100000]))->create(['quantity' => 1]);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $raw)
            ->withSession([
                CheckoutController::IDEMPOTENCY_SESSION_KEY => $oldKey,
                CheckoutController::IDEMPOTENCY_CONSUMED_KEY => true,
            ])
            ->get(route('checkout'))
            ->assertOk();

        $newKey = (string) session(CheckoutController::IDEMPOTENCY_SESSION_KEY);
        $this->assertNotSame($oldKey, $newKey);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $newKey);
    }

    private function enableSePay(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.bank_code' => 'VCB',
            'sepay.environments.sandbox.account_number' => '123456789',
            'sepay.environments.sandbox.account_name' => 'SHOP TEST',
        ]);
    }

    private function rawCartToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function key(): string
    {
        return hash('sha256', 'checkout-page-test-key');
    }

    /** @return array<string, mixed> */
    private function payload(string $method, ?string $key = null): array
    {
        return [
            'recipient_name' => 'Test User',
            'recipient_phone' => '0901234567',
            'shipping_address' => '123 Test Street',
            'note' => null,
            'payment_method' => $method,
            'idempotency_key' => $key ?? $this->key(),
        ];
    }
}
