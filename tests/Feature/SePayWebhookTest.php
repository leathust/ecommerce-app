<?php

namespace Tests\Feature;

use App\Models\SePayWebhookReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'sepay.environments.sandbox.webhook_enabled' => true,
            'sepay.environments.sandbox.webhook_secret' => 'sandbox-test-secret',
            'sepay.environments.live.webhook_enabled' => false,
            'sepay.environments.live.webhook_secret' => null,
            'queue.default' => 'sync',
        ]);
    }

    public function test_valid_raw_body_is_authenticated_persisted_and_acknowledged(): void
    {
        Queue::fake();
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;

        $response = $this->webhook($body, $timestamp);
        $response->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseHas('sepay_webhook_receipts', [
            'environment' => 'sandbox',
            'external_event_id' => '92704',
            'payload_hash' => hash('sha256', $body),
            'status' => 'queued',
        ]);
        Queue::assertPushed(\App\Jobs\ProcessSePayWebhookJob::class, 1);
    }

    public function test_signature_is_bound_to_raw_body_and_tampering_is_rejected(): void
    {
        Queue::fake();
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;
        $signature = $this->signature($body, $timestamp);

        $tampered = str_replace('5000000', '5000001', $body);

        $this->withServerVariables($this->serverVariables($timestamp, $signature))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $tampered)
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_signature']);

        $this->assertDatabaseCount('sepay_webhook_receipts', 0);
        Queue::assertNothingPushed();
    }

    public function test_missing_or_expired_hmac_is_rejected(): void
    {
        $body = $this->payloadBody();
        $expired = (string) (now()->timestamp - 301);

        $this->withServerVariables([
            'CONTENT_TYPE' => 'application/json',
        ])
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body)
            ->assertUnauthorized();

        $this->withServerVariables($this->serverVariables($expired, $this->signature($body, $expired)))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body)
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_timestamp']);
    }

    public function test_content_type_json_and_body_limits_are_enforced(): void
    {
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;
        $signature = $this->signature($body, $timestamp);

        $this->withServerVariables(array_merge($this->serverVariables($timestamp, $signature), ['CONTENT_TYPE' => 'text/plain']))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body)
            ->assertUnsupportedMediaType();

        config(['sepay.webhook_max_body_bytes' => 10]);
        $this->withServerVariables($this->serverVariables($timestamp, $this->signature($body, $timestamp)))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body)
            ->assertStatus(413)
            ->assertJson(['error' => 'body_too_large']);
    }

    public function test_malformed_or_invalid_payload_is_rejected_after_authentication(): void
    {
        $invalid = json_encode(['id' => 1, 'transferAmount' => -5], JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->timestamp;

        $this->withServerVariables($this->serverVariables($timestamp, $this->signature($invalid, $timestamp)))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $invalid)
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_payload']);

        $malformed = '{"id":';
        $this->withServerVariables($this->serverVariables($timestamp, $this->signature($malformed, $timestamp)))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $malformed)
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_json']);
    }

    public function test_duplicate_delivery_is_acknowledged_without_a_second_receipt_or_job(): void
    {
        Queue::fake();
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;
        $headers = $this->serverVariables($timestamp, $this->signature($body, $timestamp));
        $url = route('webhooks.sepay.store', ['environment' => 'sandbox']);

        $this->withServerVariables($headers)->call('POST', $url, [], [], [], [], $body)->assertOk();
        $this->withServerVariables($headers)->call('POST', $url, [], [], [], [], $body)
            ->assertOk()
            ->assertExactJson(['success' => true]);

        $this->assertDatabaseCount('sepay_webhook_receipts', 1);
        Queue::assertPushed(\App\Jobs\ProcessSePayWebhookJob::class, 1);
    }

    public function test_environment_secret_is_isolated_and_disabled_environment_returns_not_found(): void
    {
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;
        $liveUrl = route('webhooks.sepay.store', ['environment' => 'live']);

        $this->withHeaders($this->headers($timestamp, $this->signature($body, $timestamp)))
            ->call('POST', $liveUrl, [], [], [], [], $body)
            ->assertNotFound();

        $this->assertDatabaseCount('sepay_webhook_receipts', 0);
    }

    public function test_old_file_logger_route_and_admin_viewer_are_removed(): void
    {
        $this->assertNull(Route::getRoutes()->getByName('sepay.webhook'));
        $this->assertNull(Route::getRoutes()->getByName('admin.sepay.logs'));
        $this->getJson(route('webhooks.sepay.store', ['environment' => 'invalid']))
            ->assertNotFound();
    }

    public function test_raw_payload_and_sensitive_headers_are_not_written_to_application_log_contract(): void
    {
        Queue::fake();
        $body = $this->payloadBody();
        $timestamp = (string) now()->timestamp;
        $response = $this->withServerVariables($this->serverVariables($timestamp, $this->signature($body, $timestamp), [
            'HTTP_AUTHORIZATION' => 'Bearer SHOULD_NOT_PERSIST',
        ]))->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body);

        $response->assertOk();
        $receipt = SePayWebhookReceipt::query()->firstOrFail();
        $this->assertArrayHasKey('content-type', $receipt->request_headers);
        $this->assertArrayNotHasKey('authorization', $receipt->request_headers);
        $this->assertArrayNotHasKey('x-sepay-signature', $receipt->request_headers);
    }

    private function webhook(string $body, string $timestamp): \Illuminate\Testing\TestResponse
    {
        return $this->withServerVariables($this->serverVariables($timestamp, $this->signature($body, $timestamp)))
            ->call('POST', route('webhooks.sepay.store', ['environment' => 'sandbox']), [], [], [], [], $body);
    }

    /** @param array<string, string> $extra */
    /** @return array<string, string> */
    private function serverVariables(string $timestamp, string $signature, array $extra = []): array
    {
        return array_merge([
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SEPAY_TIMESTAMP' => $timestamp,
            'HTTP_X_SEPAY_SIGNATURE' => $signature,
            'HTTP_USER_AGENT' => 'SePay-Test',
        ], $extra);
    }

    /** @return array<string, string> */
    private function headers(string $timestamp, string $signature): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-SePay-Timestamp' => $timestamp,
            'X-SePay-Signature' => $signature,
            'User-Agent' => 'SePay-Test',
        ];
    }

    private function signature(string $body, string $timestamp): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, 'sandbox-test-secret');
    }

    private function payloadBody(): string
    {
        return '{"id":92704,"gateway":"Vietcombank","transactionDate":"2024-07-02 11:08:33","accountNumber":"1017588888","subAccount":"","code":"DH123456","content":"DH123456 chuyen tien","transferType":"in","description":"NGUYEN VAN A chuyen tien","transferAmount":5000000,"accumulated":105000000,"referenceCode":"FT24012345678"}';
    }
}
