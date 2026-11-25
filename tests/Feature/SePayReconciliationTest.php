<?php

namespace Tests\Feature;

use App\Events\OrderPaymentConfirmed;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentReconciliationRun;
use App\Models\SePayTransaction;
use App\Models\User;
use App\Services\PaymentManualActionService;
use App\Services\PaymentReconciliationService;
use App\Services\SePayApiClient;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class SePayReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([OrderPaymentConfirmed::class]);
        config([
            'sepay.reconciliation.page_size' => 100,
            'sepay.reconciliation.max_requests_per_second' => 3,
            'sepay.environments.sandbox.api_token' => 'test-token',
            'sepay.environments.sandbox.api_base_url' => 'https://sandbox.test',
            'sepay.environments.sandbox.api_transactions_path' => '/transactions',
        ]);
    }

    public function test_reconciliation_fetches_normalizes_and_confirms_transaction_idempotently(): void
    {
        [$attempt, $payload] = $this->fixture();
        Http::fake(['https://sandbox.test/transactions*' => Http::response([
            'status' => 200,
            'transactions' => [[
                'id' => '1001',
                'bank_brand_name' => 'Vietcombank',
                'account_number' => '123456789',
                'transaction_date' => now()->format('Y-m-d H:i:s'),
                'amount_in' => '300000.00',
                'amount_out' => '0.00',
                'code' => $payload['code'],
                'transaction_content' => $payload['code'].' chuyen tien',
            ]],
        ])]);

        $run = app(PaymentReconciliationService::class)->createRun('sandbox', now()->subHour(), now());
        $completed = app(PaymentReconciliationService::class)->process($run->id);

        $this->assertSame(PaymentReconciliationRun::STATUS_COMPLETED, $completed->status);
        $this->assertSame(1, $completed->fetched_count);
        $this->assertSame(1, $completed->matched_count);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseHas('sepay_transactions', [
            'external_transaction_id' => '1001',
            'reconciliation_run_id' => $run->id,
            'match_status' => 'matched',
        ]);

        $result = app(PaymentReconciliationService::class)->process($run->id);
        $this->assertSame(PaymentReconciliationRun::STATUS_COMPLETED, $result->status);
        $this->assertDatabaseCount('sepay_transactions', 1);
        Event::assertDispatchedTimes(OrderPaymentConfirmed::class, 1);
    }

    public function test_failed_run_can_resume_from_checkpoint_without_duplicate_confirmation(): void
    {
        [$attempt, $payload] = $this->fixture();
        config(['sepay.reconciliation.page_size' => 1]);
        Http::fake(function ($request) use ($payload) {
            $sinceId = str_contains($request->url(), 'since_id=1001') ? '1001' : null;
            if ($sinceId === null) {
                return Http::response(['status' => 200, 'transactions' => [[
                    'id' => '1001', 'account_number' => '123456789', 'transaction_date' => now()->format('Y-m-d H:i:s'),
                    'amount_in' => '300000.00', 'amount_out' => '0.00', 'code' => $payload['code'], 'transaction_content' => $payload['code'],
                ]]]);
            }
            if ($sinceId === '1001' && ! app()->bound('reconciliation_resume_second_call')) {
                app()->instance('reconciliation_resume_second_call', true);
                throw new \RuntimeException('temporary_api_failure');
            }
            return Http::response(['status' => 200, 'transactions' => []]);
        });

        $run = app(PaymentReconciliationService::class)->createRun('sandbox', now()->subHour(), now());
        $this->expectException(\RuntimeException::class);
        try {
            app(PaymentReconciliationService::class)->process($run->id);
        } finally {
            $this->assertSame(PaymentReconciliationRun::STATUS_FAILED, $run->fresh()->status);
            $this->assertSame('1001', $run->fresh()->checkpoint);
        }

        app(PaymentReconciliationService::class)->process($run->id);
        $this->assertSame(PaymentReconciliationRun::STATUS_COMPLETED, $run->fresh()->status);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseCount('sepay_transactions', 1);
        Event::assertDispatchedTimes(OrderPaymentConfirmed::class, 1);
    }

    public function test_command_dispatches_reconciliation_job_on_payments_queue(): void
    {
        Queue::fake();
        $this->artisan('sepay:reconcile', ['--environment' => 'sandbox'])
            ->expectsOutputToContain('Queued reconciliation run')
            ->assertSuccessful();
        Queue::assertPushed(\App\Jobs\ReconcileSePayTransactionsJob::class, fn ($job): bool => $job->queue === 'payments');
    }

    public function test_api_client_retries_rate_limit_without_exposing_token(): void
    {
        Http::fakeSequence('https://sandbox.test/transactions*')
            ->push(['status' => 429], 429, ['Retry-After' => '0'])
            ->push(['status' => 200, 'transactions' => []]);

        $result = app(SePayApiClient::class)->listTransactions('sandbox', now()->subMinute(), now());

        $this->assertSame([], $result['transactions']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'test-token'));
    }

    public function test_manual_match_requires_exact_guard_and_writes_admin_history(): void
    {
        [$attempt] = $this->fixture();
        $transaction = SePayTransaction::factory()->create([
            'environment' => 'sandbox',
            'external_transaction_id' => 'manual-1001',
            'account_number' => '123456789',
            'amount' => 300000,
            'direction' => 'in',
            'transfer_content' => $attempt->payment_code,
            'raw_data' => ['code' => $attempt->payment_code],
            'match_status' => 'manual_review',
            'match_reason' => 'payment_code_not_found',
        ]);
        $actor = User::factory()->create();

        app(PaymentManualActionService::class)->manualMatch($actor, $transaction, $attempt, 'Đã kiểm tra sao kê', request());

        $this->assertSame('matched', $transaction->fresh()->match_status);
        $this->assertSame('paid', $attempt->fresh()->status);
        $this->assertDatabaseHas('payment_status_histories', [
            'sepay_transaction_id' => $transaction->id,
            'source' => 'admin',
            'changed_by' => $actor->id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'payment.manual_matched', 'subject_id' => $transaction->id]);
    }

    public function test_ignore_and_refund_note_do_not_change_payment_state(): void
    {
        [$attempt] = $this->fixture();
        $transaction = SePayTransaction::factory()->create([
            'environment' => 'sandbox', 'external_transaction_id' => 'manual-1002', 'match_status' => 'unmatched',
        ]);
        $actor = User::factory()->create();
        $service = app(PaymentManualActionService::class);

        $service->ignore($actor, $transaction, 'Không thuộc đơn hàng', request());
        $service->addRefundNote($actor, $transaction->fresh(), 'Đã chuyển sang xử lý ngoài hệ thống', request());

        $this->assertSame('ignored', $transaction->fresh()->match_status);
        $this->assertSame('pending', $attempt->fresh()->status);
        $this->assertSame('Đã chuyển sang xử lý ngoài hệ thống', $transaction->fresh()->manual_note);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'payment.manual_ignored', 'subject_id' => $transaction->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['action' => 'payment.refund_note_added', 'subject_id' => $transaction->id]);
    }

    /** @return array{0: PaymentAttempt, 1: array<string, mixed>} */
    private function fixture(): array
    {
        $order = Order::factory()->create([
            'status' => 'pending', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid',
            'subtotal' => 300000, 'shipping_fee' => 0, 'total_amount' => 300000,
        ]);
        $attempt = PaymentAttempt::factory()->for($order)->create([
            'environment' => 'sandbox', 'provider' => 'sepay', 'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'expected_amount' => 300000, 'status' => 'pending', 'bank_code' => 'VCB',
            'bank_account_number' => '123456789', 'expires_at' => now()->addMinutes(10),
        ]);
        return [$attempt, ['code' => $attempt->payment_code]];
    }
}
