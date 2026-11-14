<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;
    public const STATUSES = [
        'pending' => 'Chờ xác nhận', 'confirmed' => 'Đã xác nhận',
        'processing' => 'Đang xử lý', 'shipped' => 'Đang giao',
        'delivered' => 'Đã giao', 'cancelled' => 'Đã hủy',
    ];
    public const PAYMENT_METHODS = [
        'cod' => 'Thanh toán khi nhận hàng', 'bank_transfer' => 'Chuyển khoản ngân hàng',
        'card' => 'Thẻ', 'e_wallet' => 'Ví điện tử',
    ];
    public const PAYMENT_STATUSES = [
        'unpaid' => 'Chưa thanh toán', 'paid' => 'Đã thanh toán',
        'failed' => 'Thanh toán thất bại', 'refunded' => 'Đã hoàn tiền',
    ];
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
        'delivered' => [], 'cancelled' => [],
    ];

    protected $fillable = [
        'order_code', 'checkout_idempotency_key', 'user_id', 'status', 'payment_method', 'payment_status', 'paid_at',
        'subtotal', 'shipping_fee', 'total_amount', 'recipient_name', 'recipient_phone',
        'shipping_address', 'note', 'cancel_reason', 'confirmed_at', 'processing_at',
        'shipped_at', 'delivered_at', 'cancelled_at', 'stock_deducted_at', 'stock_restored_at',
    ];
    protected $casts = [
        'subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'total_amount' => 'decimal:2',
        'paid_at' => 'datetime', 'confirmed_at' => 'datetime', 'processing_at' => 'datetime', 'shipped_at' => 'datetime',
        'delivered_at' => 'datetime', 'cancelled_at' => 'datetime',
        'stock_deducted_at' => 'datetime', 'stock_restored_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function paymentAttempts(): HasMany { return $this->hasMany(PaymentAttempt::class); }
    public function sePayTransactions(): HasMany { return $this->hasMany(SePayTransaction::class); }
    public function paymentStatusHistories(): HasMany
    {
        return $this->hasMany(PaymentStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }
    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at')->orderBy('id');
    }
    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }
    /** @return array<int, string> */
    public function allowedTransitions(): array { return self::TRANSITIONS[$this->status] ?? []; }
}
