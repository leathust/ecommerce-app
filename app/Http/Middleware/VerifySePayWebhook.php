<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifySePayWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $environment = (string) $request->route('environment');
        $supported = config('sepay.supported_environments', []);

        if (! in_array($environment, $supported, true)) {
            return response()->json(['success' => false, 'error' => 'invalid_environment'], 404);
        }

        $account = config("sepay.environments.{$environment}", []);
        $webhookEnabled = (bool) ($account['webhook_enabled'] ?? false);
        $webhookAuth = strtolower((string) ($account['webhook_auth'] ?? 'hmac'));
        $hasCredential = $webhookAuth === 'apikey'
            ? filled($account['webhook_api_key'] ?? null)
            : filled($account['webhook_secret'] ?? null);

        if (! $webhookEnabled || ! in_array($webhookAuth, ['hmac', 'apikey'], true) || ! $hasCredential) {
            return response()->json(['success' => false, 'error' => 'webhook_unavailable'], 404);
        }

        $contentType = strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        if ($contentType !== 'application/json') {
            return response()->json(['success' => false, 'error' => 'unsupported_media_type'], 415);
        }

        $rawBody = $request->getContent();
        $maxBytes = max(1, (int) config('sepay.webhook_max_body_bytes', 65536));
        if (strlen($rawBody) > $maxBytes) {
            return response()->json(['success' => false, 'error' => 'body_too_large'], 413);
        }

        $timestamp = null;

        if ($webhookAuth === 'apikey') {
            $authorization = trim((string) $request->header('Authorization', ''));
            $prefix = 'Apikey ';

            if (! str_starts_with($authorization, $prefix)
                || ! hash_equals((string) $account['webhook_api_key'], substr($authorization, strlen($prefix)))) {
                return response()->json(['success' => false, 'error' => 'invalid_api_key'], 401);
            }
        } else {
            $timestamp = (string) $request->header('X-SePay-Timestamp', '');
            $signature = trim((string) $request->header('X-SePay-Signature', ''));
            $tolerance = max(0, (int) config('sepay.webhook_timestamp_tolerance_seconds', 300));

            if (! preg_match('/^\d+$/', $timestamp) || abs(now()->timestamp - (int) $timestamp) > $tolerance) {
                return response()->json(['success' => false, 'error' => 'invalid_timestamp'], 401);
            }

            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$rawBody, (string) $account['webhook_secret']);
            if (! preg_match('/^sha256=[a-f0-9]{64}$/i', $signature) || ! hash_equals($expected, strtolower($signature))) {
                return response()->json(['success' => false, 'error' => 'invalid_signature'], 401);
            }
        }

        $decoded = json_decode($rawBody, true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return response()->json(['success' => false, 'error' => 'invalid_json'], 400);
        }

        $request->replace($decoded);
        $request->attributes->set('sepay.raw_body', $rawBody);
        $request->attributes->set('sepay.payload', $decoded);
        $request->attributes->set('sepay.environment', $environment);
        $request->attributes->set('sepay.timestamp', $timestamp);

        return $next($request);
    }
}
