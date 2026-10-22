<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = ['order_code', 'user_id', 'status', 'payment_method', 'payment_status', 'subtotal', 'shipping_fee', 'total_amount', 'recipient_name', 'recipient_phone', 'shipping_address', 'note'];
    protected $casts = ['subtotal' => 'decimal:2', 'shipping_fee' => 'decimal:2', 'total_amount' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
}
