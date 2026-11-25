<?php

namespace Tests\Feature;

use App\Events\OrderPaymentConfirmed;
use App\Jobs\ProcessSePayWebhookJob;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\SePayWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class SePayWebhookProcessingJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_processes_queued_receipt_through_matcher_and_confirms_order(): void
    {
        Event::fake([OrderPaymentConfirmed::class]);
        [$receipt, $attempt] = $this->fixture();

        (new ProcessSePayWebhookJob($receipt->id))->handle(app(\App\Services\SePayPaymentMatcher::class));

        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertSame('processed', $receipt->fresh()->status);
        $this->assertDatabaseHas('sepay_transactions', [
            'webhook_receipt_id' => $receipt->id,
            'match_status' => 'matched',
        ]);
        Event::assertDispatchedTimes(OrderPaymentConfirmed::class, 1);
    }

    public function test_replaying_the_same_job_is_a_noop(): void
    {
        Event::fake([OrderPaymentConfirmed::class]);
        [$receipt, $attempt] = $this->fixture();
        $job = new ProcessSePayWebhookJob($receipt->id);

        $job->handle(app(\App\Services\SePayPaymentMatcher::class));
        $job->handle(app(\App\Services\SePayPaymentMatcher::class));

        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseCount('sepay_transactions', 1);
        $this->assertDatabaseCount('payment_status_histories', 1);
        Event::assertDispatchedTimes(OrderPaymentConfirmed::class, 1);
    }

    /** @return array{0: SePayWebhookReceipt, 1: PaymentAttempt} */
    private function fixture(): array
    {
        $order = Order::factory()->create([
            'status' => 'pending', 'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid', 'subtotal' => 300000,
            'shipping_fee' => 0, 'total_amount' => 300000,
        ]);
        $code = 'DH'.Str::upper(Str::random(12));
        $attempt = PaymentAttempt::factory()->for($order)->create([
            'environment' => 'sandbox', 'provider' => 'sepay', 'payment_code' => $code,
            'expected_amount' => 300000, 'status' => 'pending',
            'bank_code' => 'VCB', 'bank_account_number' => '123456789',
            'expires_at' => now()->addMinutes(10),
        ]);
        $payload = [
            'id' => (string) Str::uuid(), 'gateway' => 'Vietcombank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '123456789', 'subAccount' => '', 'code' => $code,
            'content' => $code.' chuyen tien', 'transferType' => 'in',
            'description' => 'Job test', 'transferAmount' => 300000,
            'accumulated' => 1000000, 'referenceCode' => 'JOB-TEST',
        ];
        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $receipt = SePayWebhookReceipt::factory()->create([
            'environment' => 'sandbox', 'external_event_id' => $payload['id'],
            'payload_hash' => hash('sha256', $raw), 'raw_payload' => $raw, 'status' => 'queued',
        ]);

        return [$receipt, $attempt];
    }
}
