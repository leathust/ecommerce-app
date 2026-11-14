<?php

namespace Tests\Feature;

use App\Services\SePayReadinessService;
use Tests\TestCase;

class SePayInfrastructureTest extends TestCase
{
    public function test_required_backend_and_frontend_dependencies_are_installed(): void
    {
        $this->assertTrue(class_exists(\Pusher\Pusher::class));
        $this->assertTrue(class_exists(\Predis\Client::class));

        $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('laravel-echo', $package['dependencies']);
        $this->assertArrayHasKey('pusher-js', $package['dependencies']);
    }

    public function test_disabled_configuration_is_safe_and_uses_named_queues(): void
    {
        config([
            'sepay.enabled' => false,
            'sepay.environment' => 'sandbox',
            'database.redis.client' => 'predis',
        ]);

        $result = app(SePayReadinessService::class)->inspect();

        $this->assertTrue($result['ready']);
        $this->assertFalse($result['enabled']);
        $this->assertSame('payments', config('sepay.queues.payments'));
        $this->assertSame('emails', config('sepay.queues.emails'));
        $this->assertSame('broadcasts', config('sepay.queues.broadcasts'));
        $this->assertTrue(config('queue.connections.redis.after_commit'));
    }

    public function test_enabled_configuration_fails_closed_when_credentials_or_drivers_are_missing(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'database.redis.client' => 'predis',
            'queue.default' => 'sync',
            'broadcasting.default' => 'log',
            'sepay.environments.sandbox' => [
                'webhook_secret' => null,
                'api_token' => null,
                'bank_code' => null,
                'account_number' => null,
                'account_name' => null,
            ],
        ]);

        $result = app(SePayReadinessService::class)->inspect();

        $this->assertFalse($result['ready']);
        $this->assertFalse($result['checks']['sepay_webhook_secret']['ok']);
        $this->assertFalse($result['checks']['queue_connection']['ok']);
        $this->assertFalse($result['checks']['broadcast_driver']['ok']);
    }

    public function test_enabled_configuration_passes_without_exposing_secrets(): void
    {
        config([
            'sepay.enabled' => true,
            'sepay.environment' => 'sandbox',
            'database.redis.client' => 'predis',
            'queue.default' => 'redis',
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.app_id' => 'app-id',
            'broadcasting.connections.pusher.key' => 'app-key',
            'broadcasting.connections.pusher.secret' => 'DO_NOT_PRINT_THIS',
            'sepay.environments.sandbox' => [
                'webhook_secret' => 'DO_NOT_PRINT_THIS',
                'api_token' => 'DO_NOT_PRINT_THIS',
                'bank_code' => 'MBBank',
                'account_number' => '0123456789',
                'account_name' => 'SHOP',
            ],
        ]);

        $this->artisan('sepay:check')
            ->assertSuccessful()
            ->doesntExpectOutputToContain('DO_NOT_PRINT_THIS')
            ->doesntExpectOutputToContain('0123456789');
    }
}
