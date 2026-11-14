<?php

namespace App\Support;

use App\Models\User;

class StorefrontWishlistContext
{
    private bool $resolved = false;
    private ?int $resolvedUserId = null;

    /** @var array{is_customer: bool, product_ids: array<int, int>, count: int} */
    private array $resolvedData = ['is_customer' => false, 'product_ids' => [], 'count' => 0];

    /** @return array{is_customer: bool, product_ids: array<int, int>, count: int} */
    public function data(): array
    {
        $user = request()->user();
        $userId = $user instanceof User ? (int) $user->getKey() : null;

        if ($this->resolved && $this->resolvedUserId === $userId) {
            return $this->resolvedData;
        }

        $this->resolved = true;
        $this->resolvedUserId = $userId;
        $this->resolvedData = ['is_customer' => false, 'product_ids' => [], 'count' => 0];

        if (! $user instanceof User || ! $user->isActive() || ! $user->isCustomer()) {
            return $this->resolvedData;
        }

        $productIds = $user->wishlistProducts()
            ->published()
            ->pluck('products.id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return $this->resolvedData = [
            'is_customer' => true,
            'product_ids' => $productIds,
            'count' => count($productIds),
        ];
    }
}
