<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = storage_path('framework/testing/sepay-hook-test.jsonl');
        @unlink($this->logPath);
        config(['sepay.webhook_log_path' => $this->logPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);

        parent::tearDown();
    }

    public function test_webhook_accepts_json_without_csrf_and_appends_json_line(): void
    {
        $payload = [
            'id' => 123456,
            'transferAmount' => 250000,
            'content' => 'DH1001',
        ];

        $response = $this->postJson(route('sepay.webhook'), $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['request_id', 'received_at']);

        $this->assertFileExists($this->logPath);
        $entry = json_decode(trim(file_get_contents($this->logPath)), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($payload, $entry['payload']);
        $this->assertSame($response->json('request_id'), $entry['id']);
    }

    public function test_log_viewer_is_protected_by_admin_and_order_permission_middleware(): void
    {
        $route = Route::getRoutes()->getByName('admin.sepay.logs');

        $this->assertNotNull($route);
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('active', $route->gatherMiddleware());
        $this->assertContains('admin', $route->gatherMiddleware());
        $this->assertContains('permission:orders.view', $route->gatherMiddleware());

        $this->get(route('admin.sepay.logs'))->assertRedirect(route('admin.login'));
    }
}
