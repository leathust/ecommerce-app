<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ContactSubmissionMail extends Mailable
{
    public function __construct(
        public Contact $contact,
        public string $senderName,
    ) {}

    public function envelope(): Envelope
    {
        $reason = Contact::REASONS[$this->contact->reason] ?? $this->contact->reason;

        return new Envelope(
            from: new Address((string) config('mail.from.address'), $this->senderName),
            subject: 'Liên hệ mới: '.$reason,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-submission');
    }
}
