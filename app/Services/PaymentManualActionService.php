<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\SePayTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class PaymentManualActionService
{
    public function __construct(
        private readonly PaymentConfirmationService $confirmation,
        private readonly AdminAuditService $audit,
    ) {
    }

    public function manualMatch(
        User $actor,
        SePayTransaction $transaction,
        PaymentAttempt $attempt,
        string $reason,
        Request $request,
    ): SePayTransaction {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $transaction, $attempt, $reason, $request): SePayTransaction {
            $lockedTransaction = SePayTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $this->assertExactCandidate($lockedTransaction, $lockedAttempt);

            $before = $this->snapshot($lockedTransaction);
            $event = $this->confirmation->confirmExactMatch(
                $lockedTransaction,
                $lockedAttempt,
                'admin',
                (int) $actor->getKey(),
                'manual_match:'.$reason,
            );
            if ($event === null) {
                throw new RuntimeException('manual_match_confirmation_not_allowed');
            }

            $lockedTransaction->forceFill([
                'match_status' => 'matched',
                'match_reason' => 'manual_match',
                'payment_attempt_id' => $lockedAttempt->getKey(),
                'order_id' => $lockedAttempt->order_id,
                'manual_note' => $reason,
                'manual_action_by' => $actor->getKey(),
                'manual_action_at' => now(),
            ])->save();

            $this->audit->record($actor, 'payment.manual_matched', $lockedTransaction, $before, $this->snapshot($lockedTransaction), $request);
            return $lockedTransaction->fresh(['paymentAttempt', 'order']);
        });
    }

    public function ignore(User $actor, SePayTransaction $transaction, string $reason, Request $request): SePayTransaction
    {
        $reason = $this->reason($reason);

        return DB::transaction(function () use ($actor, $transaction, $reason, $request): SePayTransaction {
            $locked = SePayTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            if ($locked->match_status === 'matched') {
                throw new RuntimeException('matched_transaction_cannot_be_ignored');
            }

            $before = $this->snapshot($locked);
            $locked->forceFill([
                'match_status' => 'ignored',
                'match_reason' => 'manual_ignore',
                'manual_note' => $reason,
                'manual_action_by' => $actor->getKey(),
                'manual_action_at' => now(),
            ])->save();
            $this->audit->record($actor, 'payment.manual_ignored', $locked, $before, $this->snapshot($locked), $request);

            return $locked->fresh(['paymentAttempt', 'order']);
        });
    }

    public function addRefundNote(User $actor, SePayTransaction $transaction, string $note, Request $request): SePayTransaction
    {
        $note = $this->reason($note);

        return DB::transaction(function () use ($actor, $transaction, $note, $request): SePayTransaction {
            $locked = SePayTransaction::query()->lockForUpdate()->findOrFail($transaction->getKey());
            $before = $this->snapshot($locked);
            $locked->forceFill([
                'manual_note' => $note,
                'manual_action_by' => $actor->getKey(),
                'manual_action_at' => now(),
            ])->save();
            $this->audit->record($actor, 'payment.refund_note_added', $locked, $before, $this->snapshot($locked), $request);

            return $locked->fresh(['paymentAttempt', 'order']);
        });
    }

    private function assertExactCandidate(SePayTransaction $transaction, PaymentAttempt $attempt): void
    {
        if ($transaction->match_status === 'matched') {
            throw new RuntimeException('transaction_already_matched');
        }
        if ($transaction->direction !== 'in') {
            throw new RuntimeException('outgoing_transaction_cannot_be_matched');
        }
        if ((string) $transaction->environment !== (string) $attempt->environment
            || (int) $transaction->amount !== (int) $attempt->expected_amount
            || ! hash_equals((string) $transaction->account_number, (string) $attempt->bank_account_number)
            || $this->paymentCode($transaction) !== (string) $attempt->payment_code) {
            throw new RuntimeException('manual_match_exact_guard_failed');
        }
        if ($attempt->status !== 'pending' || $attempt->expires_at?->isPast() || $attempt->order?->status === 'cancelled') {
            throw new RuntimeException('manual_match_attempt_not_payable');
        }
    }

    private function paymentCode(SePayTransaction $transaction): ?string
    {
        $raw = is_array($transaction->raw_data) ? $transaction->raw_data : [];
        $explicit = $raw['code'] ?? null;
        if (is_string($explicit) && preg_match('/^DH[A-Z0-9]{12}$/', strtoupper(trim($explicit))) === 1) {
            return strtoupper(trim($explicit));
        }

        $content = strtoupper((string) ($raw['content'] ?? $raw['transaction_content'] ?? $transaction->transfer_content ?? ''));
        return preg_match('/\b(DH[A-Z0-9]{12})\b/', $content, $matches) === 1 ? $matches[1] : null;
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw new InvalidArgumentException('manual_reason_invalid');
        }
        return $reason;
    }

    /** @return array<string, mixed> */
    private function snapshot(SePayTransaction $transaction): array
    {
        return [
            'transaction' => [
                'id' => $transaction->getKey(),
                'external_transaction_id' => $transaction->external_transaction_id,
                'environment' => $transaction->environment,
                'match_status' => $transaction->match_status,
                'match_reason' => $transaction->match_reason,
                'payment_attempt_id' => $transaction->payment_attempt_id,
                'order_id' => $transaction->order_id,
                'manual_note' => $transaction->manual_note,
                'manual_action_by' => $transaction->manual_action_by,
            ],
        ];
    }
}
