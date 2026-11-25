<?php

namespace App\Services;

use App\Models\PaymentReconciliationRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PaymentReconciliationService
{
    public function __construct(
        private readonly SePayApiClient $client,
        private readonly SePayPaymentMatcher $matcher,
    ) {
    }

    public function createRun(string $environment, ?Carbon $from = null, ?Carbon $to = null): PaymentReconciliationRun
    {
        $to ??= now();
        $from ??= $to->clone()->subMinutes((int) config('sepay.reconciliation.lookback_minutes', 1440));

        if ($from->greaterThan($to)) {
            throw new \InvalidArgumentException('reconciliation_window_invalid');
        }

        $overlapMinutes = max(0, (int) config('sepay.reconciliation.overlap_minutes', 5));
        $windowFrom = $from->clone()->subMinutes($overlapMinutes);

        return PaymentReconciliationRun::query()->create([
            'public_id' => (string) Str::uuid(),
            'environment' => $environment,
            'status' => PaymentReconciliationRun::STATUS_PENDING,
            'checkpoint' => null,
            'metadata' => [
                'window_from' => $windowFrom->toIso8601String(),
                'window_to' => $to->toIso8601String(),
                'requested_window_from' => $from->toIso8601String(),
                'overlap_minutes' => $overlapMinutes,
                'since_id' => null,
                'last_external_id' => null,
            ],
        ]);
    }

    public function process(int $runId): PaymentReconciliationRun
    {
        $run = PaymentReconciliationRun::query()->findOrFail($runId);
        if ($run->status === PaymentReconciliationRun::STATUS_COMPLETED) {
            return $run;
        }

        $metadata = is_array($run->metadata) ? $run->metadata : [];
        $from = Carbon::parse((string) ($metadata['window_from'] ?? now()->subDay()->toIso8601String()));
        $to = Carbon::parse((string) ($metadata['window_to'] ?? now()->toIso8601String()));
        $sinceId = filled($metadata['since_id'] ?? null) ? (string) $metadata['since_id'] : null;

        $run->forceFill([
            'status' => PaymentReconciliationRun::STATUS_RUNNING,
            'started_at' => $run->started_at ?? now(),
            'finished_at' => null,
            'error_summary' => null,
        ])->save();

        try {
            do {
                $previousSinceId = $sinceId;
                $page = $this->client->listTransactions(
                    (string) $run->environment,
                    $from,
                    $to,
                    $sinceId,
                    (int) config('sepay.reconciliation.page_size', 100),
                );
                $transactions = $page['transactions'];

                foreach ($transactions as $rawTransaction) {
                    $normalized = $this->normalizeTransaction($rawTransaction);
                    $result = $this->matcher->processExternalTransaction(
                        $normalized,
                        (string) $run->environment,
                        (int) $run->getKey(),
                    );
                    $this->recordResult($run, $result, $normalized);
                    $transactionId = (string) ($normalized['id'] ?? '');
                    $metadata = array_merge($metadata, [
                        'last_external_id' => $transactionId,
                    ]);
                    $run->forceFill([
                        'checkpoint' => $transactionId,
                        'metadata' => $metadata,
                    ])->save();
                }

                $nextSinceId = $page['next_since_id'];
                $shouldContinue = $page['has_more'] && filled($nextSinceId) && $nextSinceId !== $previousSinceId;
                if ($shouldContinue) {
                    $sinceId = $nextSinceId;
                    $metadata['since_id'] = $sinceId;
                    $run->forceFill([
                        'checkpoint' => $sinceId,
                        'metadata' => $metadata,
                    ])->save();
                }
            } while ($shouldContinue);

            $run->forceFill([
                'status' => PaymentReconciliationRun::STATUS_COMPLETED,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $run->forceFill([
                'status' => PaymentReconciliationRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_summary' => 'reconciliation_failed:'.class_basename($exception),
                'metadata' => $metadata,
            ])->save();
            Log::error('sepay.reconciliation_failed', [
                'run_id' => (string) $run->public_id,
                'environment' => (string) $run->environment,
                'exception' => class_basename($exception),
            ]);
            throw $exception;
        }

        return $run->fresh();
    }

    /** @param array<string, mixed> $transaction */
    /** @return array<string, mixed> */
    public function normalizeTransaction(array $transaction): array
    {
        $amountIn = $this->amount($transaction['amount_in'] ?? $transaction['transferAmount'] ?? 0);
        $amountOut = $this->amount($transaction['amount_out'] ?? 0);
        $transferAmount = $amountIn > 0 ? $amountIn : $amountOut;

        return [
            'id' => (string) ($transaction['id'] ?? $transaction['transaction_id'] ?? ''),
            'gateway' => (string) ($transaction['bank_brand_name'] ?? $transaction['gateway'] ?? ''),
            'accountNumber' => (string) ($transaction['account_number'] ?? $transaction['accountNumber'] ?? ''),
            'transferType' => $amountOut > 0 && $amountIn === 0 ? 'out' : 'in',
            'transferAmount' => $transferAmount,
            'content' => (string) ($transaction['transaction_content'] ?? $transaction['content'] ?? ''),
            'transactionDate' => (string) ($transaction['transaction_date'] ?? $transaction['transactionDate'] ?? ''),
            'referenceCode' => (string) ($transaction['reference_number'] ?? $transaction['referenceCode'] ?? ''),
            'code' => $transaction['code'] ?? null,
            'amountIn' => $amountIn,
            'amountOut' => $amountOut,
            '_source' => 'reconciliation',
        ];
    }

    /** @param array<string, mixed> $result  @param array<string, mixed> $transaction */
    private function recordResult(PaymentReconciliationRun $run, array $result, array $transaction): void
    {
        $updates = ['fetched_count' => (int) $run->fetched_count + 1];
        if ($result['result'] === 'matched') {
            $updates['matched_count'] = (int) $run->matched_count + 1;
        } elseif (in_array($result['result'], ['manual_review', 'unmatched'], true)) {
            $updates['review_count'] = (int) $run->review_count + 1;
        }

        $run->forceFill($updates)->save();
    }

    private function amount(mixed $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }

        $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value) ?: '0';
        return max(0, (int) round((float) $normalized));
    }
}
