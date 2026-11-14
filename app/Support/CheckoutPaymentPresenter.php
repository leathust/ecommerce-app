<?php

namespace App\Support;

use App\Models\PaymentAttempt;
use RuntimeException;

class CheckoutPaymentPresenter
{
    public const POLL_WAITING_MS = 5000;

    public const POLL_MANUAL_REVIEW_MS = 15000;

    private const TERMINAL_STATES = ['paid', 'expired', 'cancelled', 'failed'];

    public function __construct(
        private readonly MoneyFormatter $money,
        private readonly SePayQrCodeUrl $qr,
    ) {
    }

    public function state(PaymentAttempt $attempt): string
    {
        $order = $attempt->order;

        if ($attempt->status === 'paid' || $order?->payment_status === 'paid') {
            return 'paid';
        }

        if ($attempt->status === 'expired') {
            return 'expired';
        }

        if ($attempt->status === 'cancelled') {
            return 'cancelled';
        }

        if ($attempt->status === 'failed') {
            return 'failed';
        }

        if ($attempt->status === 'manual_review') {
            return 'manual_review';
        }

        if ($order?->status === 'cancelled') {
            return 'cancelled';
        }

        if ($attempt->expires_at !== null && $attempt->expires_at->isPast()) {
            return 'expired';
        }

        return 'waiting';
    }

    public function shouldExpire(PaymentAttempt $attempt): bool
    {
        return $attempt->status === 'pending'
            && $this->state($attempt) === 'expired'
            && $attempt->order !== null
            && $attempt->order->payment_status !== 'paid'
            && $attempt->order->status !== 'cancelled';
    }

    public function isTerminal(string $state): bool
    {
        return in_array($state, self::TERMINAL_STATES, true);
    }

    public function page(PaymentAttempt $attempt): array
    {
        $state = $this->state($attempt);
        $amount = (int) $attempt->expected_amount;
        $paidAt = $attempt->paid_at ?? $attempt->order?->paid_at;

        return [
            'public_id' => $attempt->public_id,
            'state' => $state,
            'is_terminal' => $this->isTerminal($state),
            'order_code' => $attempt->order?->order_code,
            'order_status' => $attempt->order?->status,
            'payment_status' => $attempt->order?->payment_status,
            'amount' => $amount,
            'formatted_amount' => $this->money->format($amount),
            'bank_code' => $attempt->bank_code,
            'bank_account_number' => $attempt->bank_account_number,
            'bank_account_name' => $attempt->bank_account_name,
            'payment_code' => $attempt->payment_code,
            'qr_url' => $state === 'waiting' ? $this->safeQrUrl($attempt) : null,
            'expires_at_iso' => $attempt->expires_at?->toIso8601String(),
            'expires_at_label' => $attempt->expires_at?->format('d/m/Y H:i'),
            'remaining_seconds' => $this->remainingSeconds($attempt, $state),
            'paid_at_iso' => $paidAt?->toIso8601String(),
            'paid_at_label' => $paidAt?->format('d/m/Y H:i'),
            'poll_after_ms' => $this->pollAfterMs($state),
            'status_url' => route('checkout.payment.status', ['paymentAttempt' => $attempt->public_id]),
            'refresh_url' => route('checkout.payment', ['paymentAttempt' => $attempt->public_id]),
        ];
    }

    /** @return array{state: string, order_status: ?string, payment_status: ?string, is_terminal: bool, remaining_seconds: int, expires_at: ?string, paid_at: ?string, poll_after_ms: ?int, server_time: string} */
    public function status(PaymentAttempt $attempt): array
    {
        $state = $this->state($attempt);

        return [
            'state' => $state,
            'order_status' => $attempt->order?->status,
            'payment_status' => $attempt->order?->payment_status,
            'is_terminal' => $this->isTerminal($state),
            'remaining_seconds' => $this->remainingSeconds($attempt, $state),
            'expires_at' => $attempt->expires_at?->toIso8601String(),
            'paid_at' => ($attempt->paid_at ?? $attempt->order?->paid_at)?->toIso8601String(),
            'poll_after_ms' => $this->pollAfterMs($state),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function safeQrUrl(PaymentAttempt $attempt): ?string
    {
        try {
            return $this->qr->build($attempt);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function remainingSeconds(PaymentAttempt $attempt, string $state): int
    {
        if ($state !== 'waiting' || $attempt->expires_at === null) {
            return 0;
        }

        return max(0, $attempt->expires_at->getTimestamp() - now()->getTimestamp());
    }

    private function pollAfterMs(string $state): ?int
    {
        return match ($state) {
            'waiting' => self::POLL_WAITING_MS,
            'manual_review' => self::POLL_MANUAL_REVIEW_MS,
            default => null,
        };
    }
}
