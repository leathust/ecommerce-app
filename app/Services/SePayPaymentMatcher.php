<?php

namespace App\Services;

use App\Events\OrderPaymentConfirmed;
use App\Models\PaymentAttempt;
use App\Models\SePayTransaction;
use App\Models\SePayWebhookReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

class SePayPaymentMatcher
{
    public function __construct(private readonly PaymentConfirmationService $confirmation)
    {
    }

    /** @return array{result: string, reason_code: string, transaction_id: int, attempt_id: ?int, order_id: ?int} */
    public function processReceiptById(int $receiptId): array
    {
        return DB::transaction(function () use ($receiptId): array {
            $receipt = SePayWebhookReceipt::query()->lockForUpdate()->findOrFail($receiptId);
            $payload = json_decode((string) $receipt->raw_payload, true);

            if (! is_array($payload)) {
                throw new \RuntimeException('receipt_payload_invalid');
            }

            $result = $this->processPayload($payload, (string) $receipt->environment, $receipt);
            $receipt->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();

            return $result;
        }, 3);
    }

    /** @return array{result: string, reason_code: string, transaction_id: int, attempt_id: ?int, order_id: ?int} */
    public function processExternalTransaction(array $payload, string $environment, ?int $reconciliationRunId = null): array
    {
        return DB::transaction(function () use ($payload, $environment, $reconciliationRunId): array {
            return $this->processPayload($payload, $environment, null, $reconciliationRunId);
        }, 3);
    }

    /** @return array{result: string, reason_code: string, transaction_id: int, attempt_id: ?int, order_id: ?int} */
    private function processPayload(
        array $payload,
        string $environment,
        ?SePayWebhookReceipt $receipt = null,
        ?int $reconciliationRunId = null,
    ): array {
        $externalId = (string) ($payload['id'] ?? '');
        if ($externalId === '') {
            throw new \RuntimeException('transaction_external_id_missing');
        }

        $existing = SePayTransaction::query()
            ->where('environment', $environment)
            ->where('external_transaction_id', $externalId)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return [
                'result' => 'duplicate',
                'reason_code' => 'duplicate_external_id',
                'transaction_id' => (int) $existing->getKey(),
                'attempt_id' => $existing->payment_attempt_id ? (int) $existing->payment_attempt_id : null,
                'order_id' => $existing->order_id ? (int) $existing->order_id : null,
            ];
        }

        $transaction = $this->createTransaction($receipt, $payload, $environment, $reconciliationRunId);
        $classification = $this->classify($transaction, $payload);

        if ($classification['attempt'] instanceof PaymentAttempt && $classification['reason_code'] === 'exact_match') {
            $event = $this->confirmation->confirmExactMatch($transaction, $classification['attempt']);
            if ($event instanceof OrderPaymentConfirmed) {
                $transaction->forceFill([
                    'match_status' => 'matched',
                    'match_reason' => 'exact_match',
                    'payment_attempt_id' => $classification['attempt']->getKey(),
                    'order_id' => $classification['attempt']->order_id,
                ])->save();
                Event::dispatch($event);
                $classification['result'] = 'matched';
                $classification['reason_code'] = 'exact_match';
            } else {
                $transaction->forceFill([
                    'match_status' => 'manual_review',
                    'match_reason' => 'confirmation_not_allowed',
                    'payment_attempt_id' => $classification['attempt']->getKey(),
                    'order_id' => $classification['attempt']->order_id,
                ])->save();
                $classification['result'] = 'manual_review';
                $classification['reason_code'] = 'confirmation_not_allowed';
            }
        } else {
            $transaction->forceFill([
                'match_status' => $classification['match_status'],
                'match_reason' => $classification['reason_code'],
                'payment_attempt_id' => $classification['attempt'] instanceof PaymentAttempt ? $classification['attempt']->getKey() : null,
                'order_id' => $classification['attempt'] instanceof PaymentAttempt ? $classification['attempt']->order_id : null,
            ])->save();
        }

        return [
            'result' => $classification['result'],
            'reason_code' => $classification['reason_code'],
            'transaction_id' => (int) $transaction->getKey(),
            'attempt_id' => $classification['attempt'] instanceof PaymentAttempt ? (int) $classification['attempt']->getKey() : null,
            'order_id' => $classification['attempt'] instanceof PaymentAttempt ? (int) $classification['attempt']->order_id : null,
        ];
    }

    private function createTransaction(
        ?SePayWebhookReceipt $receipt,
        array $payload,
        string $environment,
        ?int $reconciliationRunId = null,
    ): SePayTransaction
    {
        try {
            return SePayTransaction::query()->create([
                'webhook_receipt_id' => $receipt?->getKey(),
                'reconciliation_run_id' => $reconciliationRunId,
                'environment' => $environment,
                'external_transaction_id' => (string) $payload['id'],
                'gateway' => (string) ($payload['gateway'] ?? ''),
                'account_number' => (string) ($payload['accountNumber'] ?? ''),
                'direction' => (string) ($payload['transferType'] ?? ''),
                'amount' => (int) ($payload['transferAmount'] ?? 0),
                'currency' => 'VND',
                'transfer_content' => (string) ($payload['content'] ?? ''),
                'transaction_at' => Carbon::createFromFormat('Y-m-d H:i:s', (string) $payload['transactionDate'], config('app.timezone', 'Asia/Ho_Chi_Minh')),
                'match_status' => 'unmatched',
                'raw_data' => $payload,
            ]);
        } catch (QueryException $exception) {
            throw $exception;
        }
    }

    /** @return array{attempt: ?PaymentAttempt, match_status: string, result: string, reason_code: string} */
    private function classify(SePayTransaction $transaction, array $payload): array
    {
        $code = $this->paymentCode($payload);
        $direction = (string) $transaction->direction;

        if ($direction === 'out') {
            return ['attempt' => null, 'match_status' => 'ignored', 'result' => 'ignored_outgoing', 'reason_code' => 'ignored_outgoing'];
        }

        if ($code === null) {
            return ['attempt' => null, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'missing_payment_code'];
        }

        $candidates = PaymentAttempt::query()
            ->where('provider', 'sepay')
            ->where('environment', $transaction->environment)
            ->where('payment_code', $code)
            ->lockForUpdate()
            ->get();

        if ($candidates->isEmpty()) {
            return ['attempt' => null, 'match_status' => 'unmatched', 'result' => 'unmatched', 'reason_code' => 'payment_code_not_found'];
        }

        if ($candidates->count() !== 1) {
            return ['attempt' => null, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'multiple_payment_candidates'];
        }

        /** @var PaymentAttempt $attempt */
        $attempt = $candidates->first();
        $account = (string) $transaction->account_number;
        if (! hash_equals((string) $attempt->bank_account_number, $account)) {
            return ['attempt' => null, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'account_mismatch'];
        }

        if ((int) $transaction->amount < (int) $attempt->expected_amount) {
            return ['attempt' => $attempt, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'underpayment'];
        }

        if ((int) $transaction->amount > (int) $attempt->expected_amount) {
            return ['attempt' => $attempt, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'overpayment'];
        }

        if ($attempt->status !== 'pending' || $attempt->expires_at?->isPast() || $attempt->order?->status === 'cancelled') {
            return ['attempt' => $attempt, 'match_status' => 'manual_review', 'result' => 'manual_review', 'reason_code' => 'late_payment'];
        }

        return ['attempt' => $attempt, 'match_status' => 'matched', 'result' => 'matched', 'reason_code' => 'exact_match'];
    }

    private function paymentCode(array $payload): ?string
    {
        $explicit = $payload['code'] ?? null;
        if (is_string($explicit) && preg_match('/^DH[A-Z0-9]{12}$/', strtoupper(trim($explicit))) === 1) {
            return strtoupper(trim($explicit));
        }

        $content = is_string($payload['content'] ?? null) ? strtoupper($payload['content']) : '';
        if (preg_match('/\b(DH[A-Z0-9]{12})\b/', $content, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
