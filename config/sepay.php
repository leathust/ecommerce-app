<?php

$environment = (string) env('SEPAY_MODE', 'sandbox');

$account = static fn (string $prefix): array => [
    'webhook_enabled' => (bool) env("SEPAY_{$prefix}_WEBHOOK_ENABLED", false),
    'webhook_auth' => strtolower((string) env("SEPAY_{$prefix}_WEBHOOK_AUTH", 'hmac')),
    'webhook_secret' => env("SEPAY_{$prefix}_WEBHOOK_SECRET"),
    'webhook_api_key' => env("SEPAY_{$prefix}_WEBHOOK_API_KEY"),
    'api_token' => env("SEPAY_{$prefix}_API_TOKEN"),
    'api_base_url' => rtrim((string) env("SEPAY_{$prefix}_API_BASE_URL", env('SEPAY_API_BASE_URL', 'https://userapi.sepay.vn')), '/'),
    'api_transactions_path' => '/'.trim((string) env("SEPAY_{$prefix}_API_TRANSACTIONS_PATH", env('SEPAY_API_TRANSACTIONS_PATH', 'v2/transactions')), '/'),
    'bank_code' => env("SEPAY_{$prefix}_BANK_CODE"),
    'account_number' => env("SEPAY_{$prefix}_ACCOUNT_NUMBER"),
    'account_name' => env("SEPAY_{$prefix}_ACCOUNT_NAME"),
];

return [
    'enabled' => (bool) env('SEPAY_ENABLED', false),
    'environment' => $environment,
    'supported_environments' => ['sandbox', 'live'],
    'payment_timeout_minutes' => (int) env('SEPAY_PAYMENT_TIMEOUT_MINUTES', 15),
    'payment_access_grace_minutes' => (int) env('SEPAY_PAYMENT_ACCESS_GRACE_MINUTES', 30),
    'qr_base_url' => rtrim((string) env('SEPAY_QR_BASE_URL', 'https://vietqr.app/img'), '?'),
    'qr_template' => (string) env('SEPAY_QR_TEMPLATE', 'compact'),
    'webhook_timestamp_tolerance_seconds' => (int) env('SEPAY_WEBHOOK_TOLERANCE_SECONDS', 300),
    'webhook_max_body_bytes' => (int) env('SEPAY_WEBHOOK_MAX_BODY_BYTES', 65536),
    'webhook_raw_retention_days' => (int) env('SEPAY_WEBHOOK_RAW_RETENTION_DAYS', 180),
    'api_base_url' => rtrim((string) env('SEPAY_API_BASE_URL', 'https://userapi.sepay.vn'), '/'),
    'api_transactions_path' => '/'.trim((string) env('SEPAY_API_TRANSACTIONS_PATH', 'v2/transactions'), '/'),
    'environments' => [
        'sandbox' => $account('SANDBOX'),
        'live' => $account('LIVE'),
    ],
    'queues' => [
        'payments' => env('SEPAY_PAYMENTS_QUEUE', 'payments'),
        'emails' => env('SEPAY_EMAILS_QUEUE', 'emails'),
        'broadcasts' => env('SEPAY_BROADCASTS_QUEUE', 'broadcasts'),
    ],
    'email_retry' => [
        'max_attempts' => max(1, (int) env('SEPAY_EMAIL_MAX_ATTEMPTS', 6)),
        'requeue_after_minutes' => max(1, (int) env('SEPAY_EMAIL_AUTO_RETRY_AFTER_MINUTES', 5)),
    ],
    'reconciliation' => [
        'enabled' => (bool) env('SEPAY_RECONCILIATION_ENABLED', true),
        'lookback_minutes' => max(1, (int) env('SEPAY_RECONCILIATION_LOOKBACK_MINUTES', 1440)),
        'overlap_minutes' => max(0, (int) env('SEPAY_RECONCILIATION_OVERLAP_MINUTES', 5)),
        'page_size' => min(5000, max(1, (int) env('SEPAY_RECONCILIATION_PAGE_SIZE', 100))),
        'connect_timeout' => max(1, (int) env('SEPAY_RECONCILIATION_CONNECT_TIMEOUT', 5)),
        'timeout' => max(1, (int) env('SEPAY_RECONCILIATION_TIMEOUT', 20)),
        'max_requests_per_second' => min(3, max(1, (int) env('SEPAY_RECONCILIATION_MAX_REQUESTS_PER_SECOND', 3))),
    ],

    // Temporary logger used only until Task 14.6 replaces the test endpoint.
    'webhook_log_path' => env('SEPAY_WEBHOOK_LOG_PATH', storage_path('logs/sepay-hook.jsonl')),
];
