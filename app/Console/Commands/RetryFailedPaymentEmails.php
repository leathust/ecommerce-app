<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentConfirmationEmailJob;
use App\Models\PaymentEmailDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryFailedPaymentEmails extends Command
{
    protected $signature = 'payments:retry-failed-emails {--limit=100 : Số delivery failed tối đa mỗi lần chạy}';

    protected $description = 'Tự động đưa payment email failed trở lại queue emails theo retry policy giới hạn.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $maxAttempts = (int) config('sepay.email_retry.max_attempts', 6);
        $cutoff = now()->subMinutes((int) config('sepay.email_retry.requeue_after_minutes', 5));
        $scanned = 0;
        $queued = 0;

        PaymentEmailDelivery::query()
            ->where('status', PaymentEmailDelivery::STATUS_FAILED)
            ->where('attempts', '<', $maxAttempts)
            ->whereNotNull('failed_at')
            ->where('failed_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (PaymentEmailDelivery $delivery) use (&$scanned, &$queued, $maxAttempts): void {
                $scanned++;
                $payload = DB::transaction(function () use ($delivery, $maxAttempts): ?array {
                    $locked = PaymentEmailDelivery::query()->lockForUpdate()->find($delivery->getKey());

                    if ($locked === null
                        || $locked->status !== PaymentEmailDelivery::STATUS_FAILED
                        || (int) $locked->attempts >= $maxAttempts
                        || $locked->failed_at === null
                        || $locked->failed_at->isAfter(now()->subMinutes((int) config('sepay.email_retry.requeue_after_minutes', 5)))) {
                        return null;
                    }

                    $attempt = $locked->paymentAttempt()->first();
                    if ($attempt === null) {
                        $locked->forceFill([
                            'last_error' => 'payment_attempt_missing',
                            'updated_at' => now(),
                        ])->save();

                        return null;
                    }

                    $locked->forceFill([
                        'status' => PaymentEmailDelivery::STATUS_PENDING,
                        'failed_at' => null,
                        'last_error' => 'auto_retry_queued',
                    ])->save();

                    return [
                        'payment_public_id' => (string) $attempt->public_id,
                        'event_key' => (string) $locked->event_key,
                        'delivery_id' => (int) $locked->getKey(),
                        'attempts' => (int) $locked->attempts,
                    ];
                });

                if ($payload === null) {
                    return;
                }

                try {
                    SendPaymentConfirmationEmailJob::dispatch(
                        $payload['payment_public_id'],
                        $payload['event_key'],
                    );
                    $queued++;
                    Log::notice('payment_email.auto_retry_queued', $payload + [
                        'max_attempts' => $maxAttempts,
                    ]);
                } catch (Throwable $exception) {
                    PaymentEmailDelivery::query()->whereKey($payload['delivery_id'])->update([
                        'status' => PaymentEmailDelivery::STATUS_FAILED,
                        'failed_at' => now(),
                        'last_error' => 'auto_retry_dispatch_failed:'.class_basename($exception),
                        'updated_at' => now(),
                    ]);
                }
            });

        $this->info("Scanned {$scanned}, queued {$queued} automatic payment email retries.");

        return self::SUCCESS;
    }
}
