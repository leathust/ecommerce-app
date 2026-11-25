<?php

namespace Tests\Feature;

use App\Jobs\ReconcileSePayTransactionsJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SePayOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_kill_switch_blocks_new_checkout_but_keeps_reconcile_and_webhook_paths_available(): void
    {
        config([
            'sepay.enabled' => false,
            'sepay.environment' => 'sandbox',
            'sepay.reconciliation.enabled' => true,
            'sepay.environments.sandbox.webhook_enabled' => true,
            'sepay.environments.sandbox.webhook_auth' => 'hmac',
            'sepay.environments.sandbox.webhook_secret' => 'sandbox-operations-secret',
            'sepay.environments.sandbox.api_token' => 'sandbox-api-token',
        ]);

        $checkout = app(\App\Services\SePayReadinessService::class)->checkoutConfiguration();
        $this->assertFalse($checkout['ready']);
        $this->assertSame('disabled', $checkout['status']);

        Queue::fake();
        $this->artisan('sepay:reconcile', ['--environment' => 'sandbox'])
            ->assertSuccessful();
        Queue::assertPushed(ReconcileSePayTransactionsJob::class, 1);

        $body = json_encode([
            'id' => 99114,
            'gateway' => 'SandboxBank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '0123456789',
            'code' => 'DH'.str_repeat('A', 12),
            'content' => 'DH'.str_repeat('A', 12),
            'transferType' => 'in',
            'transferAmount' => 100000,
            'referenceCode' => 'OPS-99114',
        ], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        $this->withServerVariables([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'sandbox-operations-secret'),
        ])->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('sepay_webhook_receipts', [
            'environment' => 'sandbox',
            'external_event_id' => '99114',
            'status' => 'queued',
        ]);
    }

    public function test_sandbox_and_live_profiles_keep_credentials_and_endpoints_separate(): void
    {
        config([
            'sepay.environments.sandbox.api_token' => 'sandbox-token',
            'sepay.environments.sandbox.api_base_url' => 'https://userapi-sandbox.example.test',
            'sepay.environments.sandbox.webhook_secret' => 'sandbox-secret',
            'sepay.environments.live.api_token' => 'live-token',
            'sepay.environments.live.api_base_url' => 'https://userapi.example.test',
            'sepay.environments.live.webhook_secret' => 'live-secret',
        ]);

        $sandbox = config('sepay.environments.sandbox');
        $live = config('sepay.environments.live');

        $this->assertNotSame($sandbox['api_token'], $live['api_token']);
        $this->assertNotSame($sandbox['api_base_url'], $live['api_base_url']);
        $this->assertNotSame($sandbox['webhook_secret'], $live['webhook_secret']);
    }

    public function test_operations_runbook_documents_safe_rollout_and_recovery_contract(): void
    {
        $runbook = file_get_contents(base_path('docs/sepay-payment-operations.md'));

        $this->assertIsString($runbook);
        foreach ([
            'Sandbox E2E',
            'SEPAY_ENABLED=false',
            'php artisan schedule:work',
            'php artisan queue:work redis',
            'Cloudflare',
            'Secret rotation',
            'Rollback',
            'Pusher',
            'reconciliation',
        ] as $required) {
            $this->assertStringContainsString($required, $runbook);
        }
    }

}
