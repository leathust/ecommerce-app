<?php

namespace App\Services;

use App\Events\OrderPaymentConfirmed;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\PaymentStatusHistory;
use App\Models\SePayTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

class PaymentConfirmationService
{
    public function confirmExactMatch(
        SePayTransaction $transaction,
        PaymentAttempt $attempt,
        string $source = 'webhook',
        ?int $changedBy = null,
        ?string $note = null,
    ): ?OrderPaymentConfirmed
    {
        $lockedAttempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
        $order = Order::query()->lockForUpdate()->findOrFail($lockedAttempt->order_id);

        if ($lockedAttempt->status !== 'pending'
            || $order->payment_status === 'paid'
            || $order->status === 'cancelled'
            || ($lockedAttempt->expires_at !== null && $lockedAttempt->expires_at->isPast())) {
            return null;
        }

        $now = now();
        $fromPaymentStatus = (string) $order->payment_status;
        $fromOrderStatus = (string) $order->status;

        $lockedAttempt->forceFill([
            'status' => 'paid',
            'paid_at' => $now,
        ])->save();

        $order->forceFill([
            'payment_status' => 'paid',
            'paid_at' => $now,
        ]);

        if ($order->status === 'pending') {
            $order->forceFill([
                'status' => 'confirmed',
                'confirmed_at' => $now,
            ]);
        }

        $order->save();

        PaymentStatusHistory::query()->create([
            'order_id' => $order->getKey(),
            'payment_attempt_id' => $lockedAttempt->getKey(),
            'sepay_transaction_id' => $transaction->getKey(),
            'changed_by' => $changedBy,
            'from_status' => $fromPaymentStatus,
            'to_status' => 'paid',
            'source' => $source,
            'note' => $note ?: 'exact_match',
            'metadata' => [
                'external_transaction_id' => (string) $transaction->external_transaction_id,
            ],
            'created_at' => $now,
        ]);

        if ($fromOrderStatus === 'pending' && $order->status === 'confirmed') {
            $order->statusHistories()->create([
                'from_status' => $fromOrderStatus,
                'to_status' => 'confirmed',
                'changed_by' => $changedBy,
                'note' => $note ?: 'Payment confirmed by SePay.',
                'created_at' => $now,
            ]);
        }

        return new OrderPaymentConfirmed(
            paymentPublicId: (string) $lockedAttempt->public_id,
            status: 'paid',
            paidAt: $now->toIso8601String(),
        );
    }
}
