<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

class SePayReadinessService
{
    /**
     * @return array{ready: bool, enabled: bool, environment: string, checks: array<string, array{ok: bool, message: string}>}
     */
    public function inspect(bool $connect = false): array
    {
        $enabled = (bool) config('sepay.enabled');
        $environment = (string) config('sepay.environment');
        $supported = config('sepay.supported_environments', []);
        $checks = [];

        $this->check($checks, 'environment', in_array($environment, $supported, true), 'Môi trường phải là sandbox hoặc live.');
        $this->check($checks, 'pusher_php_sdk', class_exists(\Pusher\Pusher::class), 'Pusher PHP SDK chưa được cài.');
        $this->check($checks, 'predis', class_exists(\Predis\Client::class), 'Predis chưa được cài.');
        $this->check($checks, 'redis_client', config('database.redis.client') === 'predis', 'REDIS_CLIENT phải là predis cho môi trường XAMPP hiện tại.');

        if ($enabled && in_array($environment, $supported, true)) {
            $account = config("sepay.environments.{$environment}", []);
            foreach (['webhook_secret', 'api_token', 'bank_code', 'account_number', 'account_name'] as $key) {
                $this->check($checks, "sepay_{$key}", filled($account[$key] ?? null), "Thiếu cấu hình SePay {$key} cho {$environment}.");
            }

            $this->check($checks, 'queue_connection', config('queue.default') === 'redis', 'QUEUE_CONNECTION phải là redis khi bật SePay.');
            $this->check($checks, 'broadcast_driver', config('broadcasting.default') === 'pusher', 'BROADCAST_DRIVER phải là pusher khi bật SePay.');
            foreach (['app_id', 'key', 'secret'] as $key) {
                $this->check($checks, "pusher_{$key}", filled(config("broadcasting.connections.pusher.{$key}")), "Thiếu cấu hình Pusher {$key}.");
            }
        }

        if ($connect) {
            try {
                $this->check($checks, 'redis_connection', Redis::connection()->ping() !== false, 'Không thể ping Redis.');
            } catch (Throwable) {
                $this->check($checks, 'redis_connection', false, 'Không thể kết nối Redis.');
            }
        }

        return [
            'ready' => ! collect($checks)->contains(static fn (array $check): bool => ! $check['ok']),
            'enabled' => $enabled,
            'environment' => $environment,
            'checks' => $checks,
        ];
    }

    /** @param array<string, array{ok: bool, message: string}> $checks */
    private function check(array &$checks, string $name, bool $ok, string $failureMessage): void
    {
        $checks[$name] = [
            'ok' => $ok,
            'message' => $ok ? 'OK' : $failureMessage,
        ];
    }
}
