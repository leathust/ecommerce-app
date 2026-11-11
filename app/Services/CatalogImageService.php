<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CatalogImageService
{
    public function save(Model $model, array $data, string $type): void
    {
        $oldPath = $model->image_url;
        $newPath = null;
        try {
            if (! empty($data['image'])) {
                $newPath = $data['image']->store($type, 'catalog_images');
            }
            $path = $oldPath;
            if ($data['remove_image'] ?? false) {
                $path = null;
            }
            if (! empty($data['image_url'])) {
                $path = $data['image_url'];
            }
            // An uploaded file takes priority over URL and removal checkbox.
            $path = $newPath ?? $path;
            DB::transaction(function () use ($model, $data, $path): void {
                $model->fill(collect($data)->except(['image', 'image_url', 'remove_image'])->all());
                $model->image_url = $path;
                $model->save();
            });
        } catch (Throwable $exception) {
            $this->deleteFile($newPath);
            throw $exception;
        }
        if ($oldPath !== $path) {
            $this->deleteFile($oldPath);
        }
    }

    public function deleteFile(?string $path): void
    {
        if ($path && preg_match('~^(?:categories|brands)/[a-zA-Z0-9]+\.(?:jpg|jpeg|png|webp)$~', $path)) {
            try {
                Storage::disk('catalog_images')->delete($path);
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
