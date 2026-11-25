<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Lang;

class PaymentConfirmationMail extends Mailable
{
    public function __construct(
        public Order $order,
        public PaymentAttempt $paymentAttempt,
        public string $emailLocale,
        public string $senderName,
        /** @var array{subject: string, intro: string, footer: string} */
        public array $template,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            subject: strtr($this->template['subject'] ?: Lang::get(
                'frontend.mail.payment_confirmation.subject',
                [],
                $this->emailLocale,
            ), [':order_code' => $this->order->order_code]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'order' => $this->order,
                'paymentAttempt' => $this->paymentAttempt,
                'locale' => $this->emailLocale,
                'template' => $this->template,
            ],
        );
    }
}
