<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Services\CartIdentityService;
use App\Services\SystemSettingService;

class CartPageData
{
    public function __construct(
        private readonly StorefrontProductPresenter $products,
        private readonly MoneyFormatter $money,
        private readonly SystemSettingService $settings,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(?Cart $cart, ?string $locale = null): array
    {
        $locale = $locale === 'en' ? 'en' : 'vi';
        $cartItems = $cart?->items()
            ->with(['product.primaryImage', 'product.category', 'product.brand'])
            ->orderBy('id')
            ->get() ?? collect();

        $subtotalCents = 0;
        $availableQuantity = 0;
        $availableLines = 0;

        $items = $cartItems->map(function (CartItem $item) use ($locale, &$subtotalCents, &$availableQuantity, &$availableLines): array {
            $product = $item->product;
            $presented = $this->products->present($product, $locale);
            $available = $this->isAvailable($product);
            $unitCents = $this->toCents($product->price);
            $lineCents = $unitCents * (int) $item->quantity;

            if ($available) {
                $subtotalCents += $lineCents;
                $availableQuantity += (int) $item->quantity;
                $availableLines++;
            }

            return array_merge($presented, [
                'cart_item_id' => (int) $item->getKey(),
                'cart_quantity' => (int) $item->quantity,
                'max_quantity' => max(1, min((int) $product->quantity, CartIdentityService::MAX_LINE_QUANTITY)),
                'available' => $available,
                'availability_key' => $this->availabilityKey($product),
                'line_subtotal' => number_format($lineCents / 100, 2, '.', ''),
                'formatted_line_subtotal' => $this->money->format($lineCents / 100),
            ]);
        })->all();

        $commerce = $this->settings->getMany([
            'commerce.default_shipping_fee',
            'commerce.free_shipping_threshold',
            'commerce.minimum_order_amount',
        ]);
        $shippingFeeCents = $this->toCents($commerce['commerce.default_shipping_fee']);
        $freeThresholdCents = $this->toCents($commerce['commerce.free_shipping_threshold']);
        $minimumOrderCents = $this->toCents($commerce['commerce.minimum_order_amount']);
        $freeShipping = $freeThresholdCents > 0 && $subtotalCents >= $freeThresholdCents;
        $estimatedShippingCents = $subtotalCents > 0 && ! $freeShipping ? $shippingFeeCents : 0;
        $estimatedTotalCents = $subtotalCents + $estimatedShippingCents;
        $minimumRemainingCents = max(0, $minimumOrderCents - $subtotalCents);
        $freeShippingRemainingCents = max(0, $freeThresholdCents - $subtotalCents);

        return [
            'cart_id' => $cart?->getKey(),
            'items' => $items,
            'has_items' => $items !== [],
            'available_quantity' => $availableQuantity,
            'available_lines' => $availableLines,
            'subtotal' => number_format($subtotalCents / 100, 2, '.', ''),
            'formatted_subtotal' => $this->money->format($subtotalCents / 100),
            'estimated_shipping' => number_format($estimatedShippingCents / 100, 2, '.', ''),
            'formatted_estimated_shipping' => $this->money->format($estimatedShippingCents / 100),
            'shipping_is_free' => $subtotalCents > 0 && ($freeShipping || $shippingFeeCents === 0),
            'free_shipping_enabled' => $freeThresholdCents > 0,
            'free_shipping_remaining' => $this->money->format($freeShippingRemainingCents / 100),
            'estimated_total' => number_format($estimatedTotalCents / 100, 2, '.', ''),
            'formatted_estimated_total' => $this->money->format($estimatedTotalCents / 100),
            'minimum_order_enabled' => $minimumOrderCents > 0,
            'minimum_order_met' => $minimumOrderCents === 0 || $subtotalCents >= $minimumOrderCents,
            'minimum_order_remaining' => $this->money->format($minimumRemainingCents / 100),
        ];
    }

    private function isAvailable(Product $product): bool
    {
        return (bool) $product->status
            && (bool) $product->category?->status
            && (int) $product->quantity > 0;
    }

    private function availabilityKey(Product $product): string
    {
        if (! $product->status || ! $product->category?->status) {
            return 'inactive';
        }

        return (int) $product->quantity > 0 ? 'available' : 'out_of_stock';
    }

    private function toCents(int|float|string|null $amount): int
    {
        return (int) round((float) ($amount ?? 0) * 100);
    }
}
