<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SePayTransaction extends Model
{
    use HasFactory;

    protected $table = 'sepay_transactions';

    public const MATCH_STATUSES = ['unmatched', 'matched', 'manual_review', 'ignored'];

    protected $fillable = [
        'webhook_receipt_id', 'reconciliation_run_id', 'payment_attempt_id', 'order_id', 'environment',
        'external_transaction_id', 'gateway', 'account_number', 'direction', 'amount',
        'currency', 'transfer_content', 'transaction_at', 'match_status', 'match_reason', 'manual_note',
        'manual_action_by', 'manual_action_at', 'raw_data',
    ];

    protected $casts = [
        'amount' => 'integer', 'transaction_at' => 'datetime', 'manual_action_at' => 'datetime', 'raw_data' => 'array',
    ];

    public function webhookReceipt(): BelongsTo { return $this->belongsTo(SePayWebhookReceipt::class, 'webhook_receipt_id'); }
    public function reconciliationRun(): BelongsTo { return $this->belongsTo(PaymentReconciliationRun::class, 'reconciliation_run_id'); }
    public function paymentAttempt(): BelongsTo { return $this->belongsTo(PaymentAttempt::class); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function manualActionBy(): BelongsTo { return $this->belongsTo(User::class, 'manual_action_by'); }
    public function statusHistories(): HasMany { return $this->hasMany(PaymentStatusHistory::class); }
}
