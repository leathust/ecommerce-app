<?php

namespace App\Console\Commands;

use App\Models\PaymentEmailDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPaymentEmailHealth extends Command
{
    protected $signature = 'payments:email-health {--threshold=10 : Số email failed tối đa trước khi cảnh báo}';

    protected $description = 'Kiểm tra failed payment email và phát cảnh báo vận hành nếu vượt ngưỡng.';

    public function handle(): int
    {
        $threshold = max(0, (int) $this->option('threshold'));
        $failed = PaymentEmailDelivery::query()
            ->where('status', PaymentEmailDelivery::STATUS_FAILED)
            ->count();

        $this->line("Failed payment emails: {$failed}; threshold: {$threshold}.");

        if ($failed <= $threshold) {
            return self::SUCCESS;
        }

        Log::critical('payment_email.failed_threshold_exceeded', [
            'failed_count' => $failed,
            'threshold' => $threshold,
        ]);
        $this->error('Failed payment email vượt ngưỡng cảnh báo.');

        return self::FAILURE;
    }
}
