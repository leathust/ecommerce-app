<?php

namespace App\Jobs;

use App\Services\PaymentReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReconcileSePayTransactionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public int $uniqueFor = 1800;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $runId)
    {
        $this->onQueue(config('sepay.queues.payments', 'payments'));
    }

    public function uniqueId(): string
    {
        return 'sepay-reconciliation-run:'.$this->runId;
    }

    public function handle(PaymentReconciliationService $service): void
    {
        $service->process($this->runId);
    }

    public function failed(Throwable $exception): void
    {
        // The service persists a sanitized failure state before the queue retry is recorded.
    }
}
