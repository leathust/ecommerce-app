<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['product_id', 'image_url', 'alt_text', 'sort_order', 'is_primary'];
    protected $casts = ['is_primary' => 'boolean'];

    public function product() { return $this->belongsTo(Product::class); }

    public function getImageSrcAttribute(): ?string
    {
        $path = trim((string) $this->image_url);
        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($path, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return $path;
        }

        return route('product-images.show', $this);
    }
}
