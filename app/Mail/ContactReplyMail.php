<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;

class ContactReplyMail extends Mailable
{
    public function __construct(
        public string $customerName,
        public string $replySubject,
        public string $replyMessage,
        public string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            subject: $this->replySubject,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-reply');
    }
}
