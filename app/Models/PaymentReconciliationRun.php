<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationRun extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'running', 'completed', 'failed'];

    protected $fillable = [
        'public_id', 'environment', 'status', 'initiated_by', 'checkpoint', 'fetched_count',
        'matched_count', 'review_count', 'failed_count', 'started_at', 'finished_at',
        'error_summary', 'metadata',
    ];

    protected $casts = [
        'fetched_count' => 'integer', 'matched_count' => 'integer', 'review_count' => 'integer',
        'failed_count' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'metadata' => 'array',
    ];

    public function initiatedBy(): BelongsTo { return $this->belongsTo(User::class, 'initiated_by'); }
}
