<?php

namespace App\Console\Commands;

use App\Models\PaymentAttempt;
use App\Services\CheckoutService;
use Illuminate\Console\Command;

class ExpireSePayPayments extends Command
{
    protected $signature = 'sepay:expire-payments {--limit=200 : Số payment attempt xử lý tối đa mỗi lần chạy}';

    protected $description = 'Hủy payment attempt SePay quá hạn và hoàn kho đúng một lần.';

    public function handle(CheckoutService $checkout): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $scanned = 0;
        $expired = 0;

        PaymentAttempt::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (PaymentAttempt $attempt) use ($checkout, &$scanned, &$expired): void {
                $scanned++;
                if ($checkout->expire($attempt)) {
                    $expired++;
                }
            });

        $this->info("Scanned {$scanned}, expired {$expired}.");

        return self::SUCCESS;
    }
}
