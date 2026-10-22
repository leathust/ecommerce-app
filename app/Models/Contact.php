<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    public const STATUSES = [
        'new' => 'Mới',
        'processing' => 'Đang xử lý',
        'resolved' => 'Đã giải quyết',
        'closed' => 'Đã đóng',
    ];

    public const REASONS = [
        'order' => 'Đơn hàng',
        'return_refund' => 'Đổi trả hoặc hoàn tiền',
        'warranty' => 'Bảo hành hoặc lỗi sản phẩm',
        'wholesale' => 'Hợp tác hoặc bán buôn',
        'partnership' => 'Báo chí, đối tác hoặc affiliate',
        'other' => 'Nội dung khác',
    ];

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'reason',
        'order_code',
        'message',
        'status',
    ];

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $query) => $query->where('status', $status));
    }

    public function scopeReason(Builder $query, ?string $reason): Builder
    {
        return $query->when($reason, fn (Builder $query) => $query->where('reason', $reason));
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('order_code', 'like', $like)
                    ->orWhere('message', 'like', $like);
            });
        });
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
