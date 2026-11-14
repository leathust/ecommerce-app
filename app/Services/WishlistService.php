<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Database\QueryException;

class WishlistService
{
    /** @return array{created: bool, count: int} */
    public function add(User $user, Product $product): array
    {
        try {
            $wishlist = Wishlist::query()->firstOrCreate([
                'user_id' => $user->getKey(),
                'product_id' => $product->getKey(),
            ]);

            $created = $wishlist->wasRecentlyCreated;
        } catch (QueryException $exception) {
            if (! $this->isDuplicateRace($exception, $user, $product)) {
                throw $exception;
            }

            $created = false;
        }

        return [
            'created' => $created,
            'count' => $this->count($user),
        ];
    }

    /** @return array{removed: bool, count: int} */
    public function remove(User $user, Product $product): array
    {
        $removed = Wishlist::query()
            ->forUser($user)
            ->where('product_id', $product->getKey())
            ->delete() > 0;

        return [
            'removed' => $removed,
            'count' => $this->count($user),
        ];
    }

    public function count(User $user): int
    {
        return $user->wishlistProducts()->published()->count();
    }

    private function isDuplicateRace(QueryException $exception, User $user, Product $product): bool
    {
        if (($exception->errorInfo[0] ?? null) !== '23000') {
            return false;
        }

        return Wishlist::query()
            ->forUser($user)
            ->where('product_id', $product->getKey())
            ->exists();
    }
}
