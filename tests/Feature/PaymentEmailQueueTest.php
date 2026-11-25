<?php

namespace Tests\Feature;

use App\Events\OrderPaymentConfirmed;
use App\Http\Controllers\CheckoutController;
use App\Jobs\SendPaymentConfirmationEmailJob;
use App\Mail\PaymentConfirmationMail;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentEmailDelivery;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartIdentityService;
use App\Services\SystemSettingService;
use App\Support\SystemMailMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class PaymentEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_event_queues_unique_email_job_on_emails_queue(): void
    {
        Queue::fake();
        $attempt = $this->paidAttempt();

        Event::dispatch(new OrderPaymentConfirmed(
            paymentPublicId: $attempt->public_id,
            status: 'paid',
            paidAt: $attempt->paid_at->toIso8601String(),
        ));

        Queue::assertPushed(SendPaymentConfirmationEmailJob::class, function (SendPaymentConfirmationEmailJob $job) use ($attempt): bool {
            return $job->paymentPublicId === $attempt->public_id
                && $job->eventKey === 'payment-confirmed:'.$attempt->public_id
                && $job->queue === 'emails';
        });
    }

    public function test_job_sends_customer_or_guest_snapshot_email_only_once(): void
    {
        Mail::fake();
        $attempt = $this->paidAttempt([
            'recipient_email' => 'guest@example.test',
            'locale' => 'en',
        ]);
        $job = new SendPaymentConfirmationEmailJob(
            $attempt->public_id,
            'payment-confirmed:'.$attempt->public_id,
        );

        $job->handle(app(SystemMailMetadata::class), app(SystemSettingService::class));
        $job->handle(app(SystemMailMetadata::class), app(SystemSettingService::class));

        Mail::assertSent(PaymentConfirmationMail::class, 1);
        Mail::assertSent(PaymentConfirmationMail::class, function (PaymentConfirmationMail $mail) use ($attempt): bool {
            return $mail->hasTo('guest@example.test')
                && $mail->emailLocale === 'en'
                && $mail->order->is($attempt->order);
        });
        $this->assertDatabaseHas('payment_email_deliveries', [
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_SENT,
            'attempts' => 1,
        ]);
    }

    public function test_job_uses_dynamic_admin_template_without_rendering_html_or_blade(): void
    {
        Mail::fake();
        Setting::query()->insert([
            ['setting_key' => 'mail.payment_confirmation_subject_en', 'setting_value' => 'Custom paid :order_code', 'updated_by' => null],
            ['setting_key' => 'mail.payment_confirmation_intro_en', 'setting_value' => 'Hello :name — :order_code is complete.', 'updated_by' => null],
            ['setting_key' => 'mail.payment_confirmation_footer_en', 'setting_value' => '<strong>Support</strong> {{ secret }}', 'updated_by' => null],
        ]);
        app(SystemSettingService::class)->forgetCache();
        $attempt = $this->paidAttempt([
            'recipient_email' => 'guest@example.test',
            'locale' => 'en',
            'recipient_name' => 'Template Customer',
        ]);

        (new SendPaymentConfirmationEmailJob(
            $attempt->public_id,
            'payment-confirmed:'.$attempt->public_id,
        ))->handle(app(SystemMailMetadata::class), app(SystemSettingService::class));

        Mail::assertSent(PaymentConfirmationMail::class, function (PaymentConfirmationMail $mail) use ($attempt): bool {
            $rendered = $mail->render();

            return $mail->envelope()->subject === 'Custom paid '.$attempt->order->order_code
                && str_contains($rendered, 'Hello Template Customer — '.$attempt->order->order_code.' is complete.')
                && str_contains($rendered, '&lt;strong&gt;Support&lt;/strong&gt; {{ secret }}')
                && ! str_contains($rendered, '<strong>Support</strong>');
        });
    }

    public function test_job_skips_guest_without_snapshot_email_without_sending(): void
    {
        Mail::fake();
        $attempt = $this->paidAttempt(['recipient_email' => null]);
        $job = new SendPaymentConfirmationEmailJob(
            $attempt->public_id,
            'payment-confirmed:'.$attempt->public_id,
        );

        $job->handle(app(SystemMailMetadata::class), app(SystemSettingService::class));

        Mail::assertNothingSent();
        $this->assertDatabaseHas('payment_email_deliveries', [
            'payment_attempt_id' => $attempt->id,
            'status' => PaymentEmailDelivery::STATUS_SKIPPED,
            'last_error' => 'recipient_email_missing',
        ]);
    }

    public function test_failed_job_marks_delivery_without_exposing_exception_details(): void
    {
        $attempt = $this->paidAttempt(['recipient_email' => 'buyer@example.test']);
        PaymentEmailDelivery::query()->create([
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_PENDING,
        ]);

        (new SendPaymentConfirmationEmailJob(
            $attempt->public_id,
            'payment-confirmed:'.$attempt->public_id,
        ))->failed(new RuntimeException('smtp secret should not be stored'));

        $this->assertDatabaseHas('payment_email_deliveries', [
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
            'last_error' => 'job_failed:RuntimeException',
        ]);
        $this->assertDatabaseMissing('payment_email_deliveries', [
            'last_error' => 'smtp secret should not be stored',
        ]);
    }

    public function test_manual_retry_command_requeues_only_failed_delivery(): void
    {
        Queue::fake();
        $attempt = $this->paidAttempt(['recipient_email' => 'buyer@example.test']);
        PaymentEmailDelivery::query()->create([
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
        ]);

        $this->artisan('payments:retry-email', ['paymentPublicId' => $attempt->public_id])
            ->expectsOutputToContain('Đã đưa payment email vào queue emails.')
            ->assertSuccessful();

        $this->assertDatabaseHas('payment_email_deliveries', [
            'payment_attempt_id' => $attempt->id,
            'status' => PaymentEmailDelivery::STATUS_PENDING,
            'last_error' => 'manual_retry_queued',
        ]);
        Queue::assertPushed(SendPaymentConfirmationEmailJob::class, fn (SendPaymentConfirmationEmailJob $job): bool =>
            $job->paymentPublicId === $attempt->public_id
            && $job->queue === 'emails'
        );
    }

    public function test_scheduler_command_automatically_requeues_failed_delivery_after_backoff(): void
    {
        Queue::fake();
        config([
            'sepay.email_retry.max_attempts' => 6,
            'sepay.email_retry.requeue_after_minutes' => 5,
        ]);
        $attempt = $this->paidAttempt(['recipient_email' => 'buyer@example.test']);
        PaymentEmailDelivery::query()->create([
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
            'attempts' => 3,
            'failed_at' => now()->subMinutes(6),
            'last_error' => 'job_failed:TransportException',
        ]);

        $this->artisan('payments:retry-failed-emails')
            ->expectsOutputToContain('Scanned 1, queued 1 automatic payment email retries.')
            ->assertSuccessful();

        $this->assertDatabaseHas('payment_email_deliveries', [
            'payment_attempt_id' => $attempt->id,
            'status' => PaymentEmailDelivery::STATUS_PENDING,
            'last_error' => 'auto_retry_queued',
        ]);
        Queue::assertPushed(SendPaymentConfirmationEmailJob::class, fn (SendPaymentConfirmationEmailJob $job): bool =>
            $job->paymentPublicId === $attempt->public_id
            && $job->queue === 'emails'
        );
    }

    public function test_scheduler_command_does_not_exceed_maximum_total_attempts(): void
    {
        Queue::fake();
        config([
            'sepay.email_retry.max_attempts' => 6,
            'sepay.email_retry.requeue_after_minutes' => 5,
        ]);
        $attempt = $this->paidAttempt(['recipient_email' => 'buyer@example.test']);
        PaymentEmailDelivery::query()->create([
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
            'attempts' => 6,
            'failed_at' => now()->subMinutes(30),
        ]);

        $this->artisan('payments:retry-failed-emails')
            ->expectsOutputToContain('Scanned 0, queued 0 automatic payment email retries.')
            ->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertDatabaseHas('payment_email_deliveries', [
            'payment_attempt_id' => $attempt->id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
        ]);
    }

    public function test_email_health_command_fails_when_failed_delivery_exceeds_threshold(): void
    {
        $attempt = $this->paidAttempt(['recipient_email' => 'buyer@example.test']);
        PaymentEmailDelivery::query()->create([
            'payment_attempt_id' => $attempt->id,
            'event_key' => 'payment-confirmed:'.$attempt->public_id,
            'status' => PaymentEmailDelivery::STATUS_FAILED,
        ]);

        $this->artisan('payments:email-health', ['--threshold' => 0])
            ->expectsOutputToContain('Failed payment emails: 1; threshold: 0.')
            ->assertFailed();
    }

    public function test_guest_sepay_requires_email_and_snapshots_it_on_order(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.bank_code' => 'VCB',
            'sepay.environments.sandbox.account_number' => '123456789',
            'sepay.environments.sandbox.account_name' => 'SHOP TEST',
        ]);
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 5]);
        $cart = Cart::factory()->guestWithToken($rawToken)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);
        $session = [CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key('guest-email')];
        $payload = [
            'recipient_name' => 'Guest Buyer',
            'recipient_phone' => '0901234567',
            'shipping_address' => '123 Test Street',
            'payment_method' => 'sepay',
            'idempotency_key' => $this->key('guest-email'),
        ];

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->withSession($session)
            ->post(route('checkout.store'), $payload + ['recipient_email' => 'guest@example.test'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'recipient_email' => 'guest@example.test',
            'locale' => 'vi',
        ]);
    }

    public function test_guest_sepay_requires_email(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.bank_code' => 'VCB',
            'sepay.environments.sandbox.account_number' => '123456789',
            'sepay.environments.sandbox.account_name' => 'SHOP TEST',
        ]);
        $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 5]);
        $cart = Cart::factory()->guestWithToken($rawToken)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        $this->withCookie(CartIdentityService::COOKIE_NAME, $rawToken)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key('guest-email-required')])
            ->postJson(route('checkout.store'), [
                'recipient_name' => 'Guest Buyer',
                'recipient_phone' => '0901234567',
                'shipping_address' => '123 Test Street',
                'payment_method' => 'sepay',
                'idempotency_key' => $this->key('guest-email-required'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_email']);
    }

    public function test_customer_email_is_resolved_from_account_not_browser_input(): void
    {
        $customer = User::factory()->create(['email' => 'account@example.test']);
        $product = Product::factory()->create(['price' => 100000, 'quantity' => 5]);
        $cart = Cart::factory()->forCustomer($customer)->create();
        CartItem::factory()->for($cart)->for($product)->create(['quantity' => 1]);

        $this->actingAs($customer)
            ->withSession([CheckoutController::IDEMPOTENCY_SESSION_KEY => $this->key('customer-email')])
            ->postJson(route('checkout.store'), [
                'recipient_name' => 'Customer Buyer',
                'recipient_phone' => '0901234567',
                'recipient_email' => 'attacker@example.test',
                'shipping_address' => '123 Test Street',
                'payment_method' => 'cod',
                'idempotency_key' => $this->key('customer-email'),
            ])
            ->assertCreated();

        $this->assertDatabaseHas('orders', [
            'user_id' => $customer->id,
            'recipient_email' => 'account@example.test',
        ]);
    }

    /** @param array<string, mixed> $orderOverrides */
    private function paidAttempt(array $orderOverrides = []): PaymentAttempt
    {
        $paidAt = now();
        $order = Order::factory()->create(array_merge([
            'status' => 'confirmed',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'paid',
            'paid_at' => $paidAt,
            'recipient_email' => 'customer@example.test',
            'locale' => 'vi',
        ], $orderOverrides));

        return PaymentAttempt::factory()->for($order)->create([
            'status' => 'paid',
            'paid_at' => $paidAt,
        ]);
    }

    private function key(string $suffix): string
    {
        return hash('sha256', 'payment-email-'.$suffix);
    }
}
