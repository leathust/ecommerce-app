<?php

namespace Database\Factories;

use App\Models\SePayWebhookReceipt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SePayWebhookReceipt> */
class SePayWebhookReceiptFactory extends Factory
{
    protected $model = SePayWebhookReceipt::class;

    public function definition(): array
    {
        $payload = json_encode(['id' => (string) Str::uuid()], JSON_THROW_ON_ERROR);

        return [
            'environment' => 'sandbox',
            'external_event_id' => (string) Str::uuid(),
            'payload_hash' => hash('sha256', $payload),
            'raw_payload' => $payload,
            'request_headers' => ['content-type' => ['application/json']],
            'status' => 'received',
            'attempt_count' => 0,
            'received_at' => now(),
        ];
    }
}
