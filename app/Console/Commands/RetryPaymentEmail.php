<?php

namespace App\Console\Commands;

use App\Jobs\SendPaymentConfirmationEmailJob;
use App\Models\PaymentAttempt;
use App\Models\PaymentEmailDelivery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RetryPaymentEmail extends Command
{
    protected $signature = 'payments:retry-email {paymentPublicId : Public ID của payment attempt cần retry}';

    protected $description = 'Đưa lại email thanh toán failed vào queue emails và ghi operational audit.';

    public function handle(): int
    {
        $attempt = PaymentAttempt::query()
            ->where('public_id', (string) $this->argument('paymentPublicId'))
            ->first();

        if ($attempt === null) {
            $this->error('Không tìm thấy payment attempt.');

            return self::FAILURE;
        }

        $eventKey = 'payment-confirmed:'.$attempt->public_id;
        $delivery = PaymentEmailDelivery::query()
            ->where('payment_attempt_id', $attempt->getKey())
            ->where('event_key', $eventKey)
            ->where('status', PaymentEmailDelivery::STATUS_FAILED)
            ->first();

        if ($delivery === null) {
            $this->error('Payment email chưa ở trạng thái failed để retry.');

            return self::FAILURE;
        }

        $delivery->forceFill([
            'status' => PaymentEmailDelivery::STATUS_PENDING,
            'failed_at' => null,
            'last_error' => 'manual_retry_queued',
        ])->save();

        try {
            SendPaymentConfirmationEmailJob::dispatch($attempt->public_id, $eventKey);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => PaymentEmailDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => 'manual_retry_dispatch_failed:'.class_basename($exception),
            ])->save();

            throw $exception;
        }
        Log::notice('payment_email.manual_retry_requested', [
            'payment_public_id' => $attempt->public_id,
            'delivery_id' => $delivery->getKey(),
        ]);
        $this->info('Đã đưa payment email vào queue emails.');

        return self::SUCCESS;
    }
}
