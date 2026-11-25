<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileSePayTransactionsJob;
use App\Services\PaymentReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class ReconcileSePayTransactions extends Command
{
    protected $signature = 'sepay:reconcile
        {--environment= : Environment cần đối soát, mặc định theo SEPAY_MODE}
        {--from= : Thời điểm bắt đầu, định dạng date/time của ứng dụng}
        {--to= : Thời điểm kết thúc, định dạng date/time của ứng dụng}
        {--run-id= : Public ID của run failed cần resume}
        {--sync : Chạy ngay trong tiến trình hiện tại thay vì dispatch queue}';

    protected $description = 'Đối soát giao dịch SePay theo time window và checkpoint.';

    public function handle(PaymentReconciliationService $service): int
    {
        if (! (bool) config('sepay.reconciliation.enabled', true)) {
            $this->warn('SePay reconciliation đang tắt bởi cấu hình.');
            return self::SUCCESS;
        }

        $environment = (string) ($this->option('environment') ?: config('sepay.environment', 'sandbox'));
        if (! in_array($environment, config('sepay.supported_environments', []), true)) {
            $this->error('Environment không hợp lệ.');
            return self::FAILURE;
        }

        try {
            $run = $this->resolveRun($service, $environment);
            if ($this->option('sync')) {
                $run = $service->process((int) $run->getKey());
                $this->info("Reconciliation {$run->public_id} completed with {$run->fetched_count} transactions.");
                return self::SUCCESS;
            }

            ReconcileSePayTransactionsJob::dispatch((int) $run->getKey());
            $this->info("Queued reconciliation run {$run->public_id} on payments queue.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Không thể tạo reconciliation run.');
            return self::FAILURE;
        }
    }

    private function resolveRun(PaymentReconciliationService $service, string $environment): \App\Models\PaymentReconciliationRun
    {
        $runId = $this->option('run-id');
        if (filled($runId)) {
            $run = \App\Models\PaymentReconciliationRun::query()
                ->where('public_id', (string) $runId)
                ->where('environment', $environment)
                ->firstOrFail();
            if ($run->status === \App\Models\PaymentReconciliationRun::STATUS_COMPLETED) {
                throw new \RuntimeException('reconciliation_run_completed');
            }
            return $run;
        }

        $from = $this->parseDate($this->option('from'));
        $to = $this->parseDate($this->option('to'));
        return $service->createRun($environment, $from, $to);
    }

    private function parseDate(?string $value): ?Carbon
    {
        return filled($value) ? Carbon::parse($value, config('app.timezone')) : null;
    }
}
