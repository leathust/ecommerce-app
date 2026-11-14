<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\PaymentAccessTokenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutPaymentPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('vi');
        $this->seed(RolePermissionSeeder::class);
    }

    private const GUEST_TOKEN = 'guest-payment-access-token';

    public function test_guest_with_a_valid_cookie_sees_qr_bank_details_and_countdown(): void
    {
        $attempt = $this->attempt();
        config(['sepay.environments.sandbox.bank_code' => 'OTHERBANK']);

        $response = $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]));

        $response->assertOk()
            ->assertSee(__('frontend.payment.page_title'))
            ->assertSee($attempt->payment_code)
            ->assertSee($attempt->bank_account_number)
            ->assertSee($attempt->bank_account_name)
            ->assertSee('VCB')
            ->assertSee('300.000', false)
            ->assertSee('https://vietqr.app/img', false)
            ->assertSee('acc=123456789', false)
            ->assertSee('bank=VCB', false)
            ->assertSee('amount=300000', false)
            ->assertSee('des='.$attempt->payment_code, false)
            ->assertSee('template=compact', false)
            ->assertSee('data-payment-countdown', false)
            ->assertSee('role="timer"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
    }

    public function test_customer_owner_can_view_but_other_actors_get_404(): void
    {
        $owner = User::factory()->create();
        $attempt = $this->attempt(['order' => ['user_id' => $owner->id]]);
        $url = route('checkout.payment', ['paymentAttempt' => $attempt->public_id]);

        $this->actingAs($owner)->get($url)->assertOk();

        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->actingAs(User::factory()->inactiveCustomer()->create())->get($url)->assertNotFound();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', 'admin')->firstOrFail());
        $this->actingAs($admin)->get($url)->assertNotFound();
    }

    public function test_guest_needs_the_exact_cookie_and_the_uuid_binding_is_enforced(): void
    {
        $attempt = $this->attempt();
        $url = route('checkout.payment', ['paymentAttempt' => $attempt->public_id]);

        $this->get($url)->assertNotFound();
        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, 'wrong-token')->get($url)->assertNotFound();
        $this->get(route('checkout.payment', ['paymentAttempt' => 'not-a-uuid']))->assertNotFound();
        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => (string) Str::uuid()]))
            ->assertNotFound();
    }

    public function test_query_parameters_cannot_override_amount_or_payment_code(): void
    {
        $attempt = $this->attempt();

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', [
                'paymentAttempt' => $attempt->public_id,
                'amount' => 1,
                'code' => 'HACKCODE',
            ]))
            ->assertOk()
            ->assertSee('300.000', false)
            ->assertDontSee('HACKCODE</code>', false)
            ->assertDontSee('name="amount"', false)
            ->assertDontSee('name="payment_code"', false);
    }

    public function test_bank_snapshot_is_escaped_to_prevent_xss(): void
    {
        $attempt = $this->attempt(['bank_account_name' => '<script>alert(1)</script>']);

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_status_endpoint_returns_a_minimal_allowlisted_payload_with_no_store_headers(): void
    {
        $attempt = $this->attempt();

        $response = $this->withCredentials()
            ->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->getJson(route('checkout.payment.status', ['paymentAttempt' => $attempt->public_id]));

        $response->assertOk()
            ->assertJsonPath('state', 'waiting')
            ->assertJsonPath('order_status', 'pending')
            ->assertJsonPath('payment_status', 'unpaid')
            ->assertJsonPath('is_terminal', false)
            ->assertJsonPath('poll_after_ms', 5000)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));

        $this->assertSame([
            'state', 'order_status', 'payment_status', 'is_terminal',
            'remaining_seconds', 'expires_at', 'paid_at', 'poll_after_ms', 'server_time',
        ], array_keys($response->json()));

        $this->assertArrayNotHasKey('amount', $response->json());
        $this->assertArrayNotHasKey('payment_code', $response->json());
        $this->assertArrayNotHasKey('bank_account_number', $response->json());
        $this->assertGreaterThan(0, $response->json('remaining_seconds'));
    }

    public function test_status_endpoint_is_scoped_to_the_owner_or_cookie(): void
    {
        $attempt = $this->attempt();
        $url = route('checkout.payment.status', ['paymentAttempt' => $attempt->public_id]);

        $this->getJson($url)->assertNotFound();
        $this->withCredentials()
            ->withCookie(PaymentAccessTokenService::COOKIE_NAME, 'wrong-token')
            ->getJson($url)
            ->assertNotFound();

        $owner = User::factory()->create();
        $owned = $this->attempt(['order' => ['user_id' => $owner->id], 'access_token_hash' => null]);
        $ownedUrl = route('checkout.payment.status', ['paymentAttempt' => $owned->public_id]);
        $this->actingAs($owner)->getJson($ownedUrl)->assertOk();
        $this->actingAs(User::factory()->create())->getJson($ownedUrl)->assertNotFound();
    }

    public function test_expired_attempt_is_closed_once_and_stock_is_restored_once(): void
    {
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 3]);
        $order = Order::factory()->create([
            'user_id' => null, 'status' => 'pending', 'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid', 'subtotal' => 200000, 'shipping_fee' => 0,
            'total_amount' => 200000, 'stock_deducted_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name_vi, 'sku' => $product->sku,
            'quantity' => 2, 'price' => 100000, 'total_price' => 200000,
        ]);
        $attempt = $this->attempt(['order_id' => $order->id, 'order' => [], 'expires_at' => now()->subMinute()]);
        $url = route('checkout.payment.status', ['paymentAttempt' => $attempt->public_id]);

        $first = $this->withCredentials()
            ->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->getJson($url);
        $first->assertOk()
            ->assertJsonPath('state', 'expired')
            ->assertJsonPath('is_terminal', true)
            ->assertJsonPath('remaining_seconds', 0)
            ->assertJsonPath('poll_after_ms', null);

        $this->assertSame('expired', $attempt->fresh()->status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        $this->withCredentials()
            ->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('state', 'expired');

        $this->assertSame(5, $product->fresh()->quantity);
    }

    public function test_paid_attempt_renders_the_success_state_without_qr_or_polling(): void
    {
        $order = Order::factory()->create([
            'status' => 'confirmed', 'payment_method' => 'bank_transfer',
            'payment_status' => 'paid', 'paid_at' => now(),
        ]);
        $attempt = $this->attempt(['order_id' => $order->id, 'order' => [], 'status' => 'paid', 'paid_at' => now()]);

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertSee(__('frontend.payment.paid_title'))
            ->assertSee(__('frontend.payment.paid_at_label'))
            ->assertDontSee('https://vietqr.app/img', false)
            ->assertDontSee('data-payment-status', false);
    }

    public function test_manual_review_state_keeps_polling_with_a_slower_interval(): void
    {
        $attempt = $this->attempt(['status' => 'manual_review']);

        $this->withCredentials()
            ->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->getJson(route('checkout.payment.status', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertJsonPath('state', 'manual_review')
            ->assertJsonPath('is_terminal', false)
            ->assertJsonPath('poll_after_ms', 15000);

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertSee(__('frontend.payment.manual_review_text'))
            ->assertSee('data-payment-status', false);
    }

    public function test_no_js_fallback_and_live_regions_are_rendered(): void
    {
        $attempt = $this->attempt();

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertSee('<noscript><meta http-equiv="refresh" content="15"></noscript>', false)
            ->assertSee('aria-live="polite"', false)
            ->assertSee('aria-atomic="true"', false);
    }

    public function test_payment_page_renders_in_english_when_the_storefront_locale_is_english(): void
    {
        $attempt = $this->attempt();

        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->withSession(['storefront_locale' => 'en'])
            ->get(route('checkout.payment', ['paymentAttempt' => $attempt->public_id]))
            ->assertOk()
            ->assertSee('Order payment')
            ->assertSee('Bank transfer payment');
    }

    /** @param array<string, mixed> $overrides */
    private function attempt(array $overrides = []): PaymentAttempt
    {
        $orderOverrides = $overrides['order'] ?? [];
        unset($overrides['order']);

        $order = isset($overrides['order_id'])
            ? Order::query()->findOrFail($overrides['order_id'])
            : Order::factory()->create(array_merge([
                'user_id' => null, 'status' => 'pending', 'payment_method' => 'bank_transfer',
                'payment_status' => 'unpaid', 'subtotal' => 300000, 'shipping_fee' => 0,
                'total_amount' => 300000,
            ], $orderOverrides));
        unset($overrides['order_id']);

        return PaymentAttempt::factory()->for($order)->create(array_merge([
            'environment' => 'sandbox',
            'provider' => 'sepay',
            'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'expected_amount' => 300000,
            'currency' => 'VND',
            'status' => 'pending',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456789',
            'bank_account_name' => 'SHOP TEST',
            'expires_at' => now()->addMinutes(10),
            'access_token_hash' => hash('sha256', self::GUEST_TOKEN),
        ], $overrides));
    }
}
