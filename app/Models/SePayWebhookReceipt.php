<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SePayWebhookReceipt extends Model
{
    use HasFactory;

    protected $table = 'sepay_webhook_receipts';

    public const STATUSES = ['received', 'queued', 'processed', 'duplicate', 'failed'];

    protected $fillable = [
        'environment', 'external_event_id', 'payload_hash', 'raw_payload', 'request_headers',
        'status', 'attempt_count', 'received_at', 'processed_at', 'error_message',
    ];

    protected $casts = [
        'request_headers' => 'array', 'attempt_count' => 'integer',
        'received_at' => 'datetime', 'processed_at' => 'datetime',
    ];

    public function transactions(): HasMany { return $this->hasMany(SePayTransaction::class, 'webhook_receipt_id'); }
}
