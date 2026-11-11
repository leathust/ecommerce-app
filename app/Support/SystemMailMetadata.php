<?php

namespace App\Support;

use App\Services\SystemSettingService;

class SystemMailMetadata
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function senderName(): string
    {
        $name = trim((string) $this->settings->get('mail.from_name'));

        return $name !== ''
            ? $name
            : (string) config('mail.from.name', config('app.name'));
    }

    public function contactRecipient(): ?string
    {
        $recipient = trim((string) $this->settings->get('mail.contact_recipient'));

        return filter_var($recipient, FILTER_VALIDATE_EMAIL) ? $recipient : null;
    }
}
