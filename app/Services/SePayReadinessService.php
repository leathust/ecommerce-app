<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Throwable;

class SePayReadinessService
{
    /**
     * @return array{
     *     ready: bool,
     *     enabled: bool,
     *     environment: string,
     *     checkout_ready: bool,
     *     webhook_ready: bool,
     *     queue_ready: bool,
     *     broadcast_ready: bool,
     *     checkout_checks: array<string, array{ok: bool, message: string}>,
     *     checks: array<string, array{ok: bool, message: string}>
     * }
     */
    public function inspect(bool $connect = false): array
    {
        $enabled = (bool) config('sepay.enabled');
        $environment = (string) config('sepay.environment');
        $supported = config('sepay.supported_environments', []);
        $checks = [];
        $checkout = $this->checkoutConfiguration();

        $this->check($checks, 'environment', in_array($environment, $supported, true), 'Môi trường phải là sandbox hoặc live.');
        $this->check($checks, 'pusher_php_sdk', class_exists(\Pusher\Pusher::class), 'Pusher PHP SDK chưa được cài.');
        $this->check($checks, 'predis', class_exists(\Predis\Client::class), 'Predis chưa được cài.');
        $this->check($checks, 'redis_client', config('database.redis.client') === 'predis', 'REDIS_CLIENT phải là predis cho môi trường XAMPP hiện tại.');

        $webhookReady = false;
        $queueReady = config('queue.default') === 'redis';
        $broadcastReady = config('broadcasting.default') === 'pusher'
            && collect(['app_id', 'key', 'secret'])->every(
                static fn (string $key): bool => filled(config("broadcasting.connections.pusher.{$key}")),
            );

        if ($enabled && in_array($environment, $supported, true)) {
            $account = config("sepay.environments.{$environment}", []);
            foreach (['api_token'] as $key) {
                $this->check($checks, "sepay_{$key}", filled($account[$key] ?? null), "Thiếu cấu hình SePay {$key} cho {$environment}.");
            }

            $auth = strtolower((string) ($account['webhook_auth'] ?? 'hmac'));
            $this->check($checks, 'webhook_auth', in_array($auth, ['hmac', 'apikey'], true), 'SEPAY webhook auth phải là hmac hoặc apikey.');
            $this->check(
                $checks,
                'sepay_webhook_credential',
                $auth === 'apikey' ? filled($account['webhook_api_key'] ?? null) : filled($account['webhook_secret'] ?? null),
                $auth === 'apikey' ? "Thiếu cấu hình SePay webhook API Key cho {$environment}." : "Thiếu cấu hình SePay webhook secret cho {$environment}.",
            );
            $webhookReady = (bool) ($account['webhook_enabled'] ?? false)
                && in_array($auth, ['hmac', 'apikey'], true)
                && ($auth === 'apikey' ? filled($account['webhook_api_key'] ?? null) : filled($account['webhook_secret'] ?? null));

            $this->check($checks, 'queue_connection', $queueReady, 'QUEUE_CONNECTION phải là redis khi bật xử lý webhook SePay.');
            $this->check($checks, 'broadcast_driver', config('broadcasting.default') === 'pusher', 'BROADCAST_DRIVER phải là pusher khi bật cập nhật realtime.');
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

        $infrastructureReady = ! collect($checks)->contains(static fn (array $check): bool => ! $check['ok']);

        return [
            'ready' => $infrastructureReady && (! $enabled || $checkout['ready']),
            'enabled' => $enabled,
            'environment' => $environment,
            'checkout_ready' => $checkout['ready'],
            'webhook_ready' => $webhookReady,
            'queue_ready' => $queueReady,
            'broadcast_ready' => $broadcastReady,
            'checkout_checks' => $checkout['checks'],
            'checks' => $checks,
        ];
    }

    /**
     * Readiness tối thiểu để tạo PaymentAttempt và render VietQR.
     * Webhook, Redis và Pusher thuộc các phase sau, không được khóa Checkout QR.
     *
     * @return array{ready: bool, status: 'available'|'disabled'|'unavailable', environment: string, checks: array<string, array{ok: bool, message: string}>}
     */
    public function checkoutConfiguration(): array
    {
        $enabled = (bool) config('sepay.enabled');
        $environment = (string) config('sepay.environment');
        $supported = config('sepay.supported_environments', []);
        $account = config("sepay.environments.{$environment}", []);
        $checks = [];

        $this->check($checks, 'enabled', $enabled, 'SEPAY_ENABLED đang tắt.');
        $this->check($checks, 'environment', in_array($environment, $supported, true), 'Môi trường phải là sandbox hoặc live.');
        $this->check($checks, 'bank_code', is_array($account) && filled($account['bank_code'] ?? null), 'Thiếu mã ngân hàng cho môi trường hiện tại.');
        $this->check($checks, 'account_number', is_array($account) && filled($account['account_number'] ?? null), 'Thiếu số tài khoản cho môi trường hiện tại.');

        $qrBaseUrl = (string) config('sepay.qr_base_url');
        $qrScheme = strtolower((string) parse_url($qrBaseUrl, PHP_URL_SCHEME));
        $this->check(
            $checks,
            'qr_base_url',
            filter_var($qrBaseUrl, FILTER_VALIDATE_URL) !== false && in_array($qrScheme, ['http', 'https'], true),
            'SEPAY_QR_BASE_URL không hợp lệ.',
        );

        $ready = ! collect($checks)->contains(static fn (array $check): bool => ! $check['ok']);

        return [
            'ready' => $ready,
            'status' => ! $enabled ? 'disabled' : ($ready ? 'available' : 'unavailable'),
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
