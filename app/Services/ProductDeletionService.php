<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\QueryException;

class ProductDeletionService
{
    public function __construct(private readonly ProductImageService $images) {}

    /** @return array{status: 'deleted'|'blocked', id: int} */
    public function delete(Product $product): array
    {
        $paths = $product->images()->pluck('image_url')->all();
        try {
            $product->delete();
        } catch (QueryException $exception) {
            if (! $this->isForeignKeyViolation($exception)) { throw $exception; }
            return ['status' => 'blocked', 'id' => $product->id];
        }
        $this->images->deleteFiles($paths);
        return ['status' => 'deleted', 'id' => $product->id];
    }

    private function isForeignKeyViolation(QueryException $exception): bool
    {
        return in_array((int) ($exception->errorInfo[1] ?? 0), [1451, 19], true)
            || (string) ($exception->errorInfo[0] ?? '') === '23000';
    }
}
