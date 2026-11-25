<?php

namespace App\Listeners;

use App\Events\OrderPaymentConfirmed;
use App\Jobs\SendPaymentConfirmationEmailJob;

class QueuePaymentConfirmationEmail
{
    public function handle(OrderPaymentConfirmed $event): void
    {
        SendPaymentConfirmationEmailJob::dispatch(
            paymentPublicId: $event->paymentPublicId,
            eventKey: 'payment-confirmed:'.$event->paymentPublicId,
        );
    }
}
