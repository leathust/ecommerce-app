<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'brand_id', 'sku', 'name_vi', 'name_en', 'slug', 'description_vi', 'description_en', 'price', 'quantity', 'is_featured', 'status'];
    protected $casts = ['price' => 'decimal:2', 'is_featured' => 'boolean', 'status' => 'boolean'];

    public function category() { return $this->belongsTo(Category::class); }
    public function brand() { return $this->belongsTo(Brand::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
    public function cartItems() { return $this->hasMany(CartItem::class); }
    public function wishlistItems() { return $this->hasMany(Wishlist::class); }
    public function orderItems() { return $this->hasMany(OrderItem::class); }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where($this->qualifyColumn('status'), true)
            ->whereHas('category', fn (Builder $category): Builder => $category->where('status', true));
    }

    public function scopeInStock(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('quantity'), '>', 0);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_featured'), true);
    }
}
