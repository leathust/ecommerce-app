<?php

namespace App\Jobs;

use App\Models\SePayWebhookReceipt;
use App\Services\SePayPaymentMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

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

    public function handle(SePayPaymentMatcher $matcher): void
    {
        $matcher->processReceiptById($this->receiptId);
    }

    public function failed(Throwable $exception): void
    {
        SePayWebhookReceipt::query()->whereKey($this->receiptId)->update([
            'status' => 'failed',
            'error_message' => 'processing_failed',
        ]);
    }
}
