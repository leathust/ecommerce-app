<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OrderInventoryService
{
    public function deduct(Order $order, Carbon $changedAt): void
    {
        if ($order->stock_deducted_at) { return; }

        $items = $order->items()->get(['product_id', 'product_name', 'sku', 'quantity']);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['status' => 'Không thể xác nhận đơn hàng chưa có sản phẩm.']);
        }
        if ($items->contains(fn ($item) => (int) $item->quantity <= 0)) {
            throw ValidationException::withMessages(['status' => 'Không thể xác nhận vì đơn hàng có số lượng sản phẩm không hợp lệ.']);
        }
        if ($items->contains(fn ($item) => $item->product_id === null)) {
            throw ValidationException::withMessages(['status' => 'Không thể xác nhận vì có sản phẩm trong đơn không còn tồn tại.']);
        }

        $quantities = $items->groupBy('product_id')->map(fn ($rows) => (int) $rows->sum('quantity'))->sortKeys();
        $products = Product::query()->whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        if ($products->count() !== $quantities->count()) {
            throw ValidationException::withMessages(['status' => 'Không thể xác nhận vì có sản phẩm trong đơn không còn tồn tại.']);
        }

        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            if ($product->quantity < $quantity) {
                throw ValidationException::withMessages([
                    'status' => 'Sản phẩm "'.$product->name_vi.'" không đủ tồn kho (cần '.$quantity.', còn '.$product->quantity.').',
                ]);
            }
        }
        foreach ($quantities as $productId => $quantity) {
            $product = $products->get($productId);
            $product->update(['quantity' => $product->quantity - $quantity]);
        }

        $order->stock_deducted_at = $changedAt;
    }

    public function restore(Order $order, Carbon $changedAt): void
    {
        if (! $order->stock_deducted_at || $order->stock_restored_at) { return; }

        $quantities = $order->items()->whereNotNull('product_id')->get(['product_id', 'quantity'])
            ->groupBy('product_id')->map(fn ($rows) => (int) $rows->sum('quantity'))->sortKeys();
        $products = Product::query()->whereIn('id', $quantities->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($quantities as $productId => $quantity) {
            if ($product = $products->get($productId)) {
                $product->update(['quantity' => $product->quantity + $quantity]);
            }
        }

        // Missing/deleted products are intentionally skipped; the marker prevents double restoration.
        $order->stock_restored_at = $changedAt;
    }
}
