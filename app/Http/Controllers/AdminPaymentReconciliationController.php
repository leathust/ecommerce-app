<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminPaymentManualActionRequest;
use App\Http\Requests\AdminPaymentTransactionIndexRequest;
use App\Models\PaymentAttempt;
use App\Models\PaymentReconciliationRun;
use App\Models\SePayTransaction;
use App\Models\SePayWebhookReceipt;
use App\Services\PaymentManualActionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPaymentReconciliationController extends Controller
{
    public function index(AdminPaymentTransactionIndexRequest $request): View
    {
        $filters = $request->validated();
        $transactions = $this->transactionQuery($filters)
            ->with(['order:id,order_code', 'paymentAttempt:id,public_id,payment_code,status'])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.payments.index', [
            'transactions' => $transactions,
            'runs' => PaymentReconciliationRun::query()->latest('created_at')->latest('id')->limit(10)->get(),
            'statusCounts' => SePayTransaction::query()->selectRaw('match_status, COUNT(*) as aggregate')->groupBy('match_status')->pluck('aggregate', 'match_status'),
            'metrics' => $this->metrics(),
            'filters' => $filters,
            'environments' => config('sepay.supported_environments', []),
            'matchStatuses' => SePayTransaction::MATCH_STATUSES,
        ]);
    }

    public function run(PaymentReconciliationRun $run): View
    {
        $transactions = SePayTransaction::query()
            ->where('reconciliation_run_id', $run->getKey())
            ->with(['order:id,order_code', 'paymentAttempt:id,public_id,payment_code,status'])
            ->orderByDesc('transaction_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.payments.run', compact('run', 'transactions'));
    }

    public function show(SePayTransaction $sepayTransaction): View
    {
        $sepayTransaction->load(['order', 'paymentAttempt', 'webhookReceipt', 'reconciliationRun', 'manualActionBy']);
        $attempts = PaymentAttempt::query()
            ->where('environment', $sepayTransaction->environment)
            ->where('provider', 'sepay')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id', 'public_id', 'order_id', 'payment_code', 'expected_amount', 'bank_account_number', 'status', 'expires_at']);

        return view('admin.payments.show', [
            'transaction' => $sepayTransaction,
            'attempts' => $attempts,
        ]);
    }

    public function manualMatch(
        AdminPaymentManualActionRequest $request,
        SePayTransaction $sepayTransaction,
        PaymentManualActionService $service,
    ): RedirectResponse {
        $attemptId = $request->validated('payment_attempt_id');
        if (! $attemptId) {
            return back()->withErrors(['payment_attempt_id' => 'Hãy chọn payment attempt cần khớp.']);
        }

        try {
            $service->manualMatch(
                $request->user(),
                $sepayTransaction,
                PaymentAttempt::query()->findOrFail($attemptId),
                (string) $request->validated('reason'),
                $request,
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['payment_attempt_id' => 'Không thể khớp thủ công: '.$this->safeMessage($exception)]);
        }

        return redirect()->route('admin.payments.transactions.show', $sepayTransaction)->with('success', 'Đã khớp giao dịch thanh toán thủ công.');
    }

    public function ignore(
        AdminPaymentManualActionRequest $request,
        SePayTransaction $sepayTransaction,
        PaymentManualActionService $service,
    ): RedirectResponse {
        try {
            $service->ignore($request->user(), $sepayTransaction, (string) $request->validated('reason'), $request);
        } catch (\Throwable $exception) {
            return back()->withErrors(['reason' => 'Không thể bỏ qua giao dịch: '.$this->safeMessage($exception)]);
        }

        return redirect()->route('admin.payments.transactions.show', $sepayTransaction)->with('success', 'Đã đánh dấu giao dịch là bỏ qua.');
    }

    public function refundNote(
        AdminPaymentManualActionRequest $request,
        SePayTransaction $sepayTransaction,
        PaymentManualActionService $service,
    ): RedirectResponse {
        try {
            $service->addRefundNote($request->user(), $sepayTransaction, (string) $request->validated('reason'), $request);
        } catch (\Throwable $exception) {
            return back()->withErrors(['reason' => 'Không thể lưu ghi chú: '.$this->safeMessage($exception)]);
        }

        return redirect()->route('admin.payments.transactions.show', $sepayTransaction)->with('success', 'Đã lưu ghi chú hoàn tiền, chưa thực hiện hoàn tiền tự động.');
    }

    /** @param array<string, mixed> $filters */
    private function transactionQuery(array $filters): Builder
    {
        return SePayTransaction::query()
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('external_transaction_id', 'like', $like)
                        ->orWhere('account_number', 'like', $like)
                        ->orWhere('transfer_content', 'like', $like)
                        ->orWhereHas('order', fn (Builder $order) => $order->where('order_code', 'like', $like));
                });
            })
            ->when($filters['environment'] ?? null, fn (Builder $query, string $environment) => $query->where('environment', $environment))
            ->when($filters['match_status'] ?? null, fn (Builder $query, string $status) => $query->where('match_status', $status))
            ->when($filters['match_reason'] ?? null, fn (Builder $query, string $reason) => $query->where('match_reason', $reason))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('transaction_at', '<=', $date));
    }

    private function safeMessage(\Throwable $exception): string
    {
        return match ($exception->getMessage()) {
            'manual_match_exact_guard_failed' => 'giao dịch không khớp đủ code, amount, account hoặc environment.',
            'manual_match_attempt_not_payable' => 'payment attempt đã hết hạn, hủy hoặc không còn pending.',
            'transaction_already_matched' => 'giao dịch đã được khớp trước đó.',
            'matched_transaction_cannot_be_ignored' => 'giao dịch đã matched không thể bỏ qua.',
            default => 'vui lòng kiểm tra lại trạng thái và thử lại.',
        };
    }

    /** @return array<string, int|float> */
    private function metrics(): array
    {
        $lagSamples = SePayWebhookReceipt::query()
            ->whereNotNull('processed_at')
            ->where('received_at', '>=', now()->subDay())
            ->get(['received_at', 'processed_at'])
            ->map(fn (SePayWebhookReceipt $receipt): int => max(0, (int) $receipt->received_at->diffInSeconds($receipt->processed_at)))
            ->values();

        return [
            'webhook_lag_seconds' => $lagSamples->isEmpty() ? 0 : round((float) $lagSamples->avg(), 1),
            'pending_expired_attempts' => PaymentAttempt::query()->where('status', 'pending')->where('expires_at', '<', now())->count(),
            'reconciliation_failures' => PaymentReconciliationRun::query()->where('status', PaymentReconciliationRun::STATUS_FAILED)->count(),
            'failed_webhooks' => SePayWebhookReceipt::query()->where('status', 'failed')->count(),
        ];
    }
}
