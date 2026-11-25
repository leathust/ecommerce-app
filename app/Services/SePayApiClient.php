<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SePayApiClient
{
    /** @var array<string, int> */
    private array $lastRequestAt = [];

    /**
     * @return array{transactions: array<int, array<string, mixed>>, next_since_id: ?string, has_more: bool}
     */
    public function listTransactions(
        string $environment,
        Carbon $from,
        Carbon $to,
        ?string $sinceId = null,
        ?int $limit = null,
    ): array {
        $profile = config("sepay.environments.{$environment}");
        if (! is_array($profile)) {
            throw new RuntimeException('sepay_api_environment_invalid');
        }

        $token = trim((string) ($profile['api_token'] ?? ''));
        if ($token === '') {
            throw new RuntimeException('sepay_api_token_missing');
        }

        $baseUrl = rtrim((string) ($profile['api_base_url'] ?? config('sepay.api_base_url')), '/');
        $path = '/'.trim((string) ($profile['api_transactions_path'] ?? config('sepay.api_transactions_path', 'v2/transactions')), '/');
        $pageSize = $limit ?? (int) config('sepay.reconciliation.page_size', 100);
        $query = [
            'transaction_date_min' => $from->clone()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'transaction_date_max' => $to->clone()->timezone(config('app.timezone'))->format('Y-m-d H:i:s'),
            'limit' => min(5000, max(1, $pageSize)),
        ];
        if (filled($sinceId)) {
            $query['since_id'] = (string) $sinceId;
        }

        $response = $this->request($environment, $baseUrl.$path, $token, $query);
        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('sepay_api_response_invalid');
        }

        $transactions = $payload['transactions'] ?? data_get($payload, 'data.transactions', []);
        if (! is_array($transactions)) {
            throw new RuntimeException('sepay_api_transactions_invalid');
        }

        $transactions = array_values(array_filter($transactions, 'is_array'));
        $ids = collect($transactions)
            ->map(fn (array $transaction): string => (string) ($transaction['id'] ?? $transaction['transaction_id'] ?? ''))
            ->filter(fn (string $id): bool => ctype_digit($id))
            ->map(fn (string $id): int => (int) $id)
            ->sort()
            ->values();
        $nextSinceId = $ids->isNotEmpty() ? (string) $ids->last() : null;

        return [
            'transactions' => $transactions,
            'next_since_id' => $nextSinceId,
            'has_more' => count($transactions) >= $query['limit'],
        ];
    }

    private function request(string $environment, string $url, string $token, array $query): Response
    {
        $maxAttempts = 3;
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->throttle($environment);
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout((int) config('sepay.reconciliation.connect_timeout', 5))
                ->timeout((int) config('sepay.reconciliation.timeout', 20))
                ->get($url, $query);
            $lastResponse = $response;

            if ($response->successful()) {
                $status = $response->json('status');
                if ($status !== null && (int) $status >= 400) {
                    throw new RuntimeException('sepay_api_provider_error');
                }

                return $response;
            }

            $retryable = $response->status() === 429 || $response->serverError() || $response->status() === 408;
            if (! $retryable || $attempt === $maxAttempts) {
                break;
            }

            $retryAfter = (int) ($response->header('Retry-After') ?: $response->header('x-sepay-userapi-retry-after') ?: 0);
            usleep(max(250, $retryAfter > 0 ? $retryAfter * 1_000_000 : $attempt * 500_000));
        }

        $status = $lastResponse?->status() ?? 0;
        Log::warning('sepay.reconciliation_api_failed', [
            'environment' => $environment,
            'status' => $status,
        ]);
        throw new RuntimeException('sepay_api_request_failed');
    }

    private function throttle(string $environment): void
    {
        $perSecond = min(3, max(1, (int) config('sepay.reconciliation.max_requests_per_second', 3)));
        $minimumGap = (int) ceil(1_000_000 / $perSecond);
        $last = $this->lastRequestAt[$environment] ?? 0;
        $elapsed = hrtime(true) / 1000 - $last;
        if ($last > 0 && $elapsed < $minimumGap) {
            usleep((int) ($minimumGap - $elapsed));
        }

        $this->lastRequestAt[$environment] = (int) (hrtime(true) / 1000);
    }
}
