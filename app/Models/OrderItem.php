<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public $timestamps = false;
    protected $fillable = ['order_id', 'product_id', 'product_name', 'sku', 'quantity', 'price', 'total_price'];
    protected $casts = ['price' => 'decimal:2', 'total_price' => 'decimal:2'];

    public function order() { return $this->belongsTo(Order::class); }
    public function product() { return $this->belongsTo(Product::class); }
}
