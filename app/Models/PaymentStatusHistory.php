<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStatusHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id', 'payment_attempt_id', 'sepay_transaction_id', 'changed_by',
        'from_status', 'to_status', 'source', 'note', 'metadata', 'created_at',
    ];

    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function paymentAttempt(): BelongsTo { return $this->belongsTo(PaymentAttempt::class); }
    public function sePayTransaction(): BelongsTo { return $this->belongsTo(SePayTransaction::class); }
    public function changedBy(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
