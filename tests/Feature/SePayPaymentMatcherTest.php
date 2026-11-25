<?php

namespace Tests\Feature;

use App\Events\OrderPaymentConfirmed;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\SePayWebhookReceipt;
use App\Services\SePayPaymentMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class SePayPaymentMatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderPaymentConfirmed::class]);
    }

    public function test_exact_match_confirms_payment_order_and_history_after_commit(): void
    {
        [$attempt, $receipt] = $this->fixture();

        $result = app(SePayPaymentMatcher::class)->processReceiptById($receipt->id);

        $this->assertSame('matched', $result['result']);
        $this->assertSame('exact_match', $result['reason_code']);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertNotNull($attempt->fresh()->paid_at);
        $this->assertSame('paid', $attempt->order->fresh()->payment_status);
        $this->assertSame('confirmed', $attempt->order->fresh()->status);
        $this->assertSame('matched', $attempt->transactions()->firstOrFail()->match_status);
        $this->assertDatabaseHas('payment_status_histories', [
            'order_id' => $attempt->order_id,
            'payment_attempt_id' => $attempt->id,
            'to_status' => 'paid',
            'source' => 'webhook',
            'note' => 'exact_match',
        ]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $attempt->order_id,
            'from_status' => 'pending',
            'to_status' => 'confirmed',
            'changed_by' => null,
        ]);
        Event::assertDispatched(OrderPaymentConfirmed::class, function (OrderPaymentConfirmed $event) use ($attempt): bool {
            return $event->paymentPublicId === $attempt->public_id
                && $event->status === 'paid';
        });
    }

    public function test_underpayment_is_manual_review_and_does_not_change_order(): void
    {
        [$attempt, $receipt] = $this->fixture(['transferAmount' => 299999]);

        $result = app(SePayPaymentMatcher::class)->processReceiptById($receipt->id);
        $transaction = $attempt->transactions()->firstOrFail();

        $this->assertSame('manual_review', $result['result']);
        $this->assertSame('underpayment', $result['reason_code']);
        $this->assertSame('manual_review', $transaction->match_status);
        $this->assertSame('underpayment', $transaction->match_reason);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('unpaid', $attempt->order->fresh()->payment_status);
        Event::assertNotDispatched(OrderPaymentConfirmed::class);
    }

    public function test_overpayment_wrong_account_and_outgoing_are_not_auto_paid(): void
    {
        [$attempt, $overReceipt] = $this->fixture(['transferAmount' => 300001]);
        $over = app(SePayPaymentMatcher::class)->processReceiptById($overReceipt->id);
        $this->assertSame('overpayment', $over['reason_code']);

        [$wrongAttempt, $wrongReceipt] = $this->fixture(['accountNumber' => '999999999']);
        $wrong = app(SePayPaymentMatcher::class)->processReceiptById($wrongReceipt->id);
        $this->assertSame('account_mismatch', $wrong['reason_code']);
        $this->assertSame('pending', $wrongAttempt->fresh()->status);

        [$outAttempt, $outReceipt] = $this->fixture(['transferType' => 'out']);
        $out = app(SePayPaymentMatcher::class)->processReceiptById($outReceipt->id);
        $this->assertSame('ignored_outgoing', $out['result']);
        $this->assertSame('ignored', \App\Models\SePayTransaction::query()->where('webhook_receipt_id', $outReceipt->id)->firstOrFail()->match_status);
        $this->assertSame('pending', $outAttempt->fresh()->status);
    }

    public function test_late_payment_is_manual_review_and_does_not_resurrect_attempt(): void
    {
        [$attempt, $receipt] = $this->fixture([], ['expires_at' => now()->subMinute()]);

        $result = app(SePayPaymentMatcher::class)->processReceiptById($receipt->id);

        $this->assertSame('late_payment', $result['reason_code']);
        $this->assertSame('manual_review', $attempt->transactions()->firstOrFail()->match_status);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('unpaid', $attempt->order->fresh()->payment_status);
    }

    public function test_provider_normalization_failure_rolls_back_transaction_and_keeps_receipt_recoverable(): void
    {
        [$attempt, $receipt] = $this->fixture();
        $payload = json_decode($receipt->raw_payload, true, flags: JSON_THROW_ON_ERROR);
        $payload['transactionDate'] = 'invalid-date';
        $receipt->forceFill(['raw_payload' => json_encode($payload, JSON_THROW_ON_ERROR)])->save();

        $this->expectException(\Carbon\Exceptions\InvalidFormatException::class);
        try {
            app(SePayPaymentMatcher::class)->processReceiptById($receipt->id);
        } finally {
            $this->assertDatabaseCount('sepay_transactions', 0);
            $this->assertSame('queued', $receipt->fresh()->status);
            $this->assertSame('pending', $attempt->fresh()->status);
            Event::assertNotDispatched(OrderPaymentConfirmed::class);
        }
    }

    public function test_duplicate_external_transaction_is_idempotent_and_emits_one_event(): void
    {
        [$attempt, $receipt] = $this->fixture();
        $matcher = app(SePayPaymentMatcher::class);

        $first = $matcher->processReceiptById($receipt->id);
        $second = $matcher->processReceiptById($receipt->id);

        $this->assertSame('matched', $first['result']);
        $this->assertSame('duplicate', $second['result']);
        $this->assertDatabaseCount('sepay_transactions', 1);
        $this->assertDatabaseCount('payment_status_histories', 1);
        Event::assertDispatchedTimes(OrderPaymentConfirmed::class, 1);
    }

    /** @param array<string, mixed> $payloadOverrides
     *  @param array<string, mixed> $attemptOverrides
     *  @return array{0: PaymentAttempt, 1: SePayWebhookReceipt}
     */
    private function fixture(array $payloadOverrides = [], array $attemptOverrides = []): array
    {
        $order = Order::factory()->create([
            'status' => 'pending',
            'payment_method' => 'bank_transfer',
            'payment_status' => 'unpaid',
            'subtotal' => 300000,
            'shipping_fee' => 0,
            'total_amount' => 300000,
        ]);

        $attempt = PaymentAttempt::factory()->for($order)->create(array_merge([
            'environment' => 'sandbox',
            'provider' => 'sepay',
            'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'expected_amount' => 300000,
            'status' => 'pending',
            'bank_code' => 'VCB',
            'bank_account_number' => '123456789',
            'expires_at' => now()->addMinutes(10),
        ], $attemptOverrides));

        $payload = array_merge([
            'id' => (string) Str::uuid(),
            'gateway' => 'Vietcombank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '123456789',
            'subAccount' => '',
            'code' => $attempt->payment_code,
            'content' => $attempt->payment_code.' chuyen tien',
            'transferType' => 'in',
            'description' => 'Test transfer',
            'transferAmount' => 300000,
            'accumulated' => 1000000,
            'referenceCode' => 'REF-'.Str::upper(Str::random(8)),
        ], $payloadOverrides);

        $raw = json_encode($payload, JSON_THROW_ON_ERROR);
        $receipt = SePayWebhookReceipt::factory()->create([
            'environment' => 'sandbox',
            'external_event_id' => (string) $payload['id'],
            'payload_hash' => hash('sha256', $raw),
            'raw_payload' => $raw,
            'status' => 'queued',
        ]);

        return [$attempt, $receipt];
    }
}
