<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactReplyMail extends Mailable
{
    public function __construct(
        public string $customerName,
        public string $replySubject,
        public string $replyMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->replySubject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-reply');
    }
}
