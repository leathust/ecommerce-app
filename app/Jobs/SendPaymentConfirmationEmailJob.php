<?php

namespace App\Jobs;

use App\Mail\PaymentConfirmationMail;
use App\Models\PaymentAttempt;
use App\Models\PaymentEmailDelivery;
use App\Services\SystemSettingService;
use App\Support\SystemMailMetadata;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendPaymentConfirmationEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 86400;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $paymentPublicId,
        public readonly string $eventKey,
    ) {
        $this->onQueue(config('sepay.queues.emails', 'emails'));
    }

    public function uniqueId(): string
    {
        return $this->eventKey;
    }

    public function handle(SystemMailMetadata $mailMetadata, SystemSettingService $settings): void
    {
        $attempt = PaymentAttempt::query()
            ->with(['order.items'])
            ->where('public_id', $this->paymentPublicId)
            ->firstOrFail();
        $order = $attempt->order;

        if ($order === null) {
            throw new \RuntimeException('payment_confirmation_order_missing');
        }

        $delivery = PaymentEmailDelivery::query()->firstOrCreate(
            ['event_key' => $this->eventKey],
            [
                'payment_attempt_id' => $attempt->getKey(),
                'status' => PaymentEmailDelivery::STATUS_PENDING,
            ],
        );

        if (in_array($delivery->status, [PaymentEmailDelivery::STATUS_SENT, PaymentEmailDelivery::STATUS_SKIPPED], true)) {
            return;
        }

        $delivery->increment('attempts');

        if (! is_string($order->recipient_email) || trim($order->recipient_email) === '') {
            $delivery->forceFill([
                'status' => PaymentEmailDelivery::STATUS_SKIPPED,
                'failed_at' => null,
                'last_error' => 'recipient_email_missing',
            ])->save();

            return;
        }

        $locale = in_array($order->locale, ['vi', 'en'], true) ? $order->locale : 'vi';
        $template = [
            'subject' => (string) $settings->get('mail.payment_confirmation_subject_'.$locale),
            'intro' => (string) $settings->get('mail.payment_confirmation_intro_'.$locale),
            'footer' => (string) $settings->get('mail.payment_confirmation_footer_'.$locale),
        ];

        Mail::to($order->recipient_email)->send(new PaymentConfirmationMail(
            order: $order,
            paymentAttempt: $attempt,
            emailLocale: $locale,
            senderName: $mailMetadata->senderName(),
            template: $template,
        ));

        $delivery->forceFill([
            'status' => PaymentEmailDelivery::STATUS_SENT,
            'sent_at' => now(),
            'failed_at' => null,
            'last_error' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        PaymentEmailDelivery::query()
            ->where('event_key', $this->eventKey)
            ->update([
                'status' => PaymentEmailDelivery::STATUS_FAILED,
                'failed_at' => now(),
                'last_error' => 'job_failed:'.class_basename($exception),
                'updated_at' => now(),
            ]);
    }
}
