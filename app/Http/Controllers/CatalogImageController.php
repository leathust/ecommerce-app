<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;

class CatalogImageController extends Controller
{
    public function show(string $type, int $id)
    {
        $model = match ($type) {
            'categories' => Category::findOrFail($id),
            'brands' => Brand::findOrFail($id),
            default => abort(404),
        };
        $admin = auth()->user();
        abort_unless($model->status || ($admin && $admin->isActive() && $admin->isAdmin()
            && ($admin->hasPermission($type.'.view') || $admin->hasPermission($type.'.update'))), 404);
        $path = $model->image_url ?? '';
        abort_unless(preg_match('~^'.$type.'/[a-zA-Z0-9]+\.(?:jpg|jpeg|png|webp)$~', $path), 404);
        $disk = Storage::disk('catalog_images');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file($disk->path($path), ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
