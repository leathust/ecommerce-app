<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentAttempt extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'paid', 'expired', 'cancelled', 'manual_review', 'failed'];

    protected $fillable = [
        'public_id', 'order_id', 'environment', 'provider', 'payment_code', 'idempotency_key',
        'access_token_hash', 'expected_amount', 'currency', 'status', 'bank_code',
        'bank_account_number', 'bank_account_name', 'expires_at', 'paid_at', 'cancelled_at', 'metadata',
    ];

    protected $casts = [
        'expected_amount' => 'integer', 'expires_at' => 'datetime', 'paid_at' => 'datetime',
        'cancelled_at' => 'datetime', 'metadata' => 'array',
    ];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function transactions(): HasMany { return $this->hasMany(SePayTransaction::class); }
    public function statusHistories(): HasMany { return $this->hasMany(PaymentStatusHistory::class); }
}
