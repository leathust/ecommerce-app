<?php

namespace App\Http\Controllers;

use App\Models\ProductImage;
use Illuminate\Support\Facades\Storage;

class ProductImageController extends Controller
{
    public function show(ProductImage $image)
    {
        abort_unless(preg_match('~^\d+/[a-zA-Z0-9]+\.(?:jpg|jpeg|png|webp)$~', $image->image_url), 404);
        $product = $image->product;
        $admin = auth()->user();
        abort_unless($product && ($product->status || ($admin && $admin->isActive() && $admin->isAdmin()
            && ($admin->hasPermission('products.view') || $admin->hasPermission('products.update')))), 404);
        $disk = Storage::disk('product_images');
        abort_unless($disk->exists($image->image_url), 404);
        $mime = $disk->mimeType($image->image_url);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file($disk->path($image->image_url), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
