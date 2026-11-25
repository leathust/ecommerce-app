<?php

namespace Tests\Feature;

use App\Events\OrderPaymentConfirmed;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\User;
use App\Services\PaymentAccessTokenService;
use App\Support\CheckoutPaymentPresenter;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentRealtimeTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST_TOKEN = 'payment-realtime-guest-token';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'broadcasting.connections.pusher.key' => 'test-pusher-key',
            'broadcasting.connections.pusher.secret' => 'test-pusher-secret',
            'broadcasting.connections.pusher.app_id' => 'test-pusher-app',
        ]);
    }

    public function test_payment_event_has_private_channel_queue_and_allowlisted_payload(): void
    {
        $event = new OrderPaymentConfirmed('payment-public-id', 'paid', '2026-11-25T10:00:00+07:00');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('broadcasts', $event->broadcastQueue);
        $this->assertInstanceOf(PrivateChannel::class, $event->broadcastOn());
        $this->assertSame('private-payment.payment-public-id', $event->broadcastOn()->name);
        $this->assertSame('payment.confirmed', $event->broadcastAs());
        $this->assertSame([
            'payment_public_id' => 'payment-public-id',
            'status' => 'paid',
            'paid_at' => '2026-11-25T10:00:00+07:00',
        ], $event->broadcastWith());
    }

    public function test_guest_with_valid_payment_cookie_can_authorize_private_channel(): void
    {
        $attempt = $this->attempt();

        $response = $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, self::GUEST_TOKEN)
            ->post('/checkout/payments/broadcast-auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-payment.'.$attempt->public_id,
            ]);

        $response->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_private_channel_rejects_missing_or_wrong_guest_cookie(): void
    {
        $attempt = $this->attempt();
        $payload = [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-payment.'.$attempt->public_id,
        ];

        $this->post('/checkout/payments/broadcast-auth', $payload)->assertForbidden();
        $this->withCookie(PaymentAccessTokenService::COOKIE_NAME, 'wrong-token')
            ->post('/checkout/payments/broadcast-auth', $payload)
            ->assertForbidden();
    }

    public function test_private_channel_is_scoped_to_customer_owner(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $attempt = $this->attempt(['order' => ['user_id' => $owner->id], 'access_token_hash' => null]);
        $payload = [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-payment.'.$attempt->public_id,
        ];

        $this->actingAs($owner)->post('/checkout/payments/broadcast-auth', $payload)->assertOk();
        $this->actingAs($other)->post('/checkout/payments/broadcast-auth', $payload)->assertForbidden();
    }

    public function test_payment_auth_path_preserves_subdirectory_cookie_scope(): void
    {
        config(['app.url' => 'https://checkout.test/ecommerce-app']);
        $page = app(CheckoutPaymentPresenter::class)->page($this->attempt());

        $this->assertSame('/ecommerce-app/checkout/payments/broadcast-auth', $page['broadcast_auth_url']);
    }

    /** @param array<string, mixed> $overrides */
    private function attempt(array $overrides = []): PaymentAttempt
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'subtotal' => 300000,
            'shipping_fee' => 0,
            'total_amount' => 300000,
            ...($overrides['order'] ?? []),
        ]);

        unset($overrides['order']);

        return PaymentAttempt::factory()->for($order)->create([
            'public_id' => (string) Str::uuid(),
            'environment' => 'sandbox',
            'provider' => 'sepay',
            'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'expected_amount' => 300000,
            'status' => 'pending',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456789',
            'expires_at' => now()->addMinutes(10),
            'access_token_hash' => hash('sha256', self::GUEST_TOKEN),
            ...$overrides,
        ]);
    }
}
