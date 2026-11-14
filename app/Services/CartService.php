<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    /** @return array{created: bool, item_quantity: int, cart_count: int, line_count: int, subtotal: string} */
    public function add(Cart $cart, Product $product, int $quantity): array
    {
        $created = false;
        $itemQuantity = DB::transaction(function () use ($cart, $product, $quantity, &$created): int {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());
            $lockedProduct = Product::query()->with('category')->lockForUpdate()->findOrFail($product->getKey());
            $this->ensureAvailable($lockedProduct);

            $item = $lockedCart->items()->forProduct($lockedProduct)->lockForUpdate()->first();
            $targetQuantity = (int) ($item?->quantity ?? 0) + $quantity;
            $this->ensureWithinStock($lockedProduct, $targetQuantity);

            if ($item) {
                $item->update(['quantity' => $targetQuantity]);
            } else {
                $item = $lockedCart->items()->create([
                    'product_id' => $lockedProduct->getKey(),
                    'quantity' => $targetQuantity,
                ]);
                $created = true;
            }

            return (int) $item->quantity;
        }, 3);

        return array_merge([
            'created' => $created,
            'item_quantity' => $itemQuantity,
        ], $this->summary($cart));
    }

    /** @return array{item_quantity: int, cart_count: int, line_count: int, subtotal: string} */
    public function update(Cart $cart, Product $product, int $quantity): array
    {
        DB::transaction(function () use ($cart, $product, $quantity): void {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());
            $lockedProduct = Product::query()->with('category')->lockForUpdate()->findOrFail($product->getKey());
            $item = $lockedCart->items()->forProduct($lockedProduct)->lockForUpdate()->first();

            if (! $item) {
                throw (new ModelNotFoundException())->setModel(CartItem::class);
            }

            $this->ensureAvailable($lockedProduct);
            $this->ensureWithinStock($lockedProduct, $quantity);
            $item->update(['quantity' => $quantity]);
        }, 3);

        return array_merge(['item_quantity' => $quantity], $this->summary($cart));
    }

    /** @return array{removed: bool, cart_count: int, line_count: int, subtotal: string} */
    public function remove(?Cart $cart, Product $product): array
    {
        $removed = $cart ? DB::transaction(function () use ($cart, $product): bool {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());

            return $lockedCart->items()->forProduct($product)->delete() > 0;
        }, 3) : false;

        return array_merge(['removed' => $removed], $this->summary($cart));
    }

    /** @return array{removed_lines: int, cart_count: int, line_count: int, subtotal: string} */
    public function clear(?Cart $cart): array
    {
        $removedLines = $cart ? DB::transaction(function () use ($cart): int {
            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->getKey());

            return $lockedCart->items()->delete();
        }, 3) : 0;

        return array_merge(['removed_lines' => $removedLines], $this->summary($cart));
    }

    /** @return array{cart_count: int, line_count: int, subtotal: string} */
    public function summary(?Cart $cart): array
    {
        if (! $cart) {
            return ['cart_count' => 0, 'line_count' => 0, 'subtotal' => '0.00'];
        }

        $items = $cart->items()
            ->with(['product.category'])
            ->get()
            ->filter(fn (CartItem $item): bool => $this->isAvailable($item->product));

        $subtotalCents = $items->sum(function (CartItem $item): int {
            $unitCents = (int) round((float) $item->product->price * 100);

            return $unitCents * (int) $item->quantity;
        });

        return [
            'cart_count' => (int) $items->sum('quantity'),
            'line_count' => $items->count(),
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
        ];
    }

    private function ensureAvailable(Product $product): void
    {
        if (! $this->isAvailable($product)) {
            throw ValidationException::withMessages([
                'quantity' => __('frontend.cart.product_unavailable'),
            ]);
        }
    }

    private function ensureWithinStock(Product $product, int $quantity): void
    {
        $limit = min((int) $product->quantity, CartIdentityService::MAX_LINE_QUANTITY);

        if ($quantity > $limit) {
            throw ValidationException::withMessages([
                'quantity' => __('frontend.cart.insufficient_stock', ['count' => $limit]),
            ]);
        }
    }

    private function isAvailable(?Product $product): bool
    {
        return $product !== null
            && (bool) $product->status
            && (bool) $product->category?->status
            && (int) $product->quantity > 0;
    }
}
