<?php

namespace App\Jobs;

use App\Models\SePayWebhookReceipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSePayWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 20;

    public int $uniqueFor = 900;

    public function __construct(public readonly int $receiptId)
    {
        $this->onQueue(config('sepay.queues.payments', 'payments'));
    }

    public function uniqueId(): string
    {
        return 'sepay-webhook-receipt:'.$this->receiptId;
    }

    public function handle(): void
    {
        // Business matching is deliberately owned by Task 14.7. Keeping the
        // receipt queued makes the durable handoff observable and recoverable.
        SePayWebhookReceipt::query()->whereKey($this->receiptId)->lockForUpdate()->first();
    }

    public function failed(\Throwable $exception): void
    {
        SePayWebhookReceipt::query()->whereKey($this->receiptId)->update([
            'status' => 'failed',
            'error_message' => 'processing_failed',
        ]);
    }
}
