<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentReconciliationRun;
use App\Models\PaymentStatusHistory;
use App\Models\SePayTransaction;
use App\Models\SePayWebhookReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_models_expose_relations_casts_and_factories(): void
    {
        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->for($order)->create([
            'expected_amount' => 150000,
            'metadata' => ['channel' => 'qr'],
        ]);
        $receipt = SePayWebhookReceipt::factory()->create();
        $transaction = SePayTransaction::factory()->create([
            'webhook_receipt_id' => $receipt->id,
            'payment_attempt_id' => $attempt->id,
            'order_id' => $order->id,
            'amount' => 150000,
        ]);
        $history = PaymentStatusHistory::factory()->create([
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'sepay_transaction_id' => $transaction->id,
            'to_status' => 'paid',
        ]);
        $run = PaymentReconciliationRun::factory()->create(['metadata' => ['page' => 1]]);

        $this->assertTrue($attempt->order->is($order));
        $this->assertTrue($transaction->webhookReceipt->is($receipt));
        $this->assertTrue($transaction->paymentAttempt->is($attempt));
        $this->assertTrue($history->order->is($order));
        $this->assertTrue($order->paymentAttempts->first()->is($attempt));
        $this->assertTrue($order->sePayTransactions->first()->is($transaction));
        $this->assertSame(150000, $attempt->expected_amount);
        $this->assertSame(['channel' => 'qr'], $attempt->metadata);
        $this->assertSame(['page' => 1], $run->metadata);
        $this->assertNotNull($transaction->transaction_at);
    }

    public function test_provider_identifiers_are_unique_inside_each_environment(): void
    {
        $receipt = SePayWebhookReceipt::factory()->create([
            'environment' => 'sandbox',
            'external_event_id' => 'EVENT-001',
        ]);
        SePayWebhookReceipt::factory()->create([
            'environment' => 'live',
            'external_event_id' => $receipt->external_event_id,
        ]);

        try {
            SePayWebhookReceipt::factory()->create([
                'environment' => 'sandbox',
                'external_event_id' => $receipt->external_event_id,
            ]);
            $this->fail('Duplicate event in one environment must be rejected.');
        } catch (QueryException) {
            $this->assertDatabaseCount('sepay_webhook_receipts', 2);
        }

        $transaction = SePayTransaction::factory()->create([
            'environment' => 'sandbox',
            'external_transaction_id' => 'TX-001',
        ]);
        SePayTransaction::factory()->create([
            'environment' => 'live',
            'external_transaction_id' => $transaction->external_transaction_id,
        ]);

        $this->expectException(QueryException::class);
        SePayTransaction::factory()->create([
            'environment' => 'sandbox',
            'external_transaction_id' => $transaction->external_transaction_id,
        ]);
    }

    public function test_order_with_any_payment_record_cannot_be_hard_deleted(): void
    {
        $order = Order::factory()->create();
        $attempt = PaymentAttempt::factory()->for($order)->create();
        $transaction = SePayTransaction::factory()->create([
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
        ]);
        $history = PaymentStatusHistory::factory()->create([
            'order_id' => $order->id,
            'payment_attempt_id' => $attempt->id,
            'sepay_transaction_id' => $transaction->id,
        ]);

        try {
            $order->delete();
            $this->fail('An order with payment records must not be hard deleted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('orders', ['id' => $order->id]);
            $this->assertDatabaseHas('payment_attempts', ['id' => $attempt->id, 'order_id' => $order->id]);
            $this->assertDatabaseHas('payment_status_histories', ['id' => $history->id, 'order_id' => $order->id]);
            $this->assertDatabaseHas('sepay_transactions', ['id' => $transaction->id, 'order_id' => $order->id]);
        }
    }

    public function test_raw_webhook_retention_defaults_to_180_days(): void
    {
        $this->assertSame(180, config('sepay.webhook_raw_retention_days'));
        $this->assertGreaterThanOrEqual(90, config('sepay.webhook_raw_retention_days'));
        $this->assertLessThanOrEqual(180, config('sepay.webhook_raw_retention_days'));
    }

    public function test_migration_round_trip_preserves_existing_orders_and_does_not_backfill_paid(): void
    {
        $order = Order::factory()->create(['payment_status' => 'unpaid']);
        $migration = require database_path('migrations/2026_11_14_000030_create_payment_infrastructure_tables.php');

        $migration->down();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid']);
        $this->assertFalse(Schema::hasColumn('orders', 'paid_at'));
        $this->assertFalse(Schema::hasTable('payment_attempts'));

        $migration->up();
        $this->assertTrue(Schema::hasColumn('orders', 'paid_at'));
        $this->assertTrue(Schema::hasTable('payment_reconciliation_runs'));
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'paid_at' => null]);
    }
}
