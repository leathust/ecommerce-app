<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['category_id', 'brand_id', 'sku', 'name_vi', 'name_en', 'slug', 'description_vi', 'description_en', 'price', 'quantity', 'is_featured', 'status'];
    protected $casts = ['price' => 'decimal:2', 'is_featured' => 'boolean', 'status' => 'boolean'];

    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function cartItems() { return $this->hasMany(CartItem::class); }
    public function wishlistItems() { return $this->hasMany(Wishlist::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }
}
