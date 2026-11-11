<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use Database\Seeders\CustomerSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_seeder_creates_consistent_registered_and_guest_orders_without_changing_stock(): void
    {
        Storage::fake('product_images');
        $this->seed([CustomerSeeder::class, ProductSeeder::class]);
        $stockBefore = Product::where('sku', 'like', 'DEMO-%')->pluck('quantity', 'id')->all();

        $this->seed(OrderSeeder::class);

        $orders = Order::where('order_code', 'like', 'DEMO-ORDER-%');
        $this->assertSame(48, (clone $orders)->count());
        $this->assertSame(12, (clone $orders)->whereNull('user_id')->count());
        $this->assertSame(36, (clone $orders)->whereNotNull('user_id')->count());
        foreach (array_keys(Order::STATUSES) as $status) {
            $this->assertSame(8, (clone $orders)->where('status', $status)->count());
        }
        $this->assertSame(0, (clone $orders)->whereNotNull('stock_deducted_at')->count());
        $this->assertSame(0, (clone $orders)->whereNotNull('stock_restored_at')->count());
        $this->assertSame($stockBefore, Product::where('sku', 'like', 'DEMO-%')->pluck('quantity', 'id')->all());

        foreach ((clone $orders)->with('items')->get() as $order) {
            $this->assertGreaterThanOrEqual(1, $order->items->count());
            $this->assertLessThanOrEqual(4, $order->items->count());
            $subtotal = $order->items->sum(fn (OrderItem $item) => (float) $item->total_price);
            $this->assertSame((float) $order->subtotal, $subtotal);
            $this->assertSame((float) $order->total_amount, $subtotal + (float) $order->shipping_fee);
            $this->assertSame($order->status, $order->statusHistories()->get()->last()->to_status);
        }
    }

    public function test_order_items_are_snapshots_and_seeder_is_idempotent(): void
    {
        Storage::fake('product_images');
        $this->seed(OrderSeeder::class);
        $item = OrderItem::whereHas('order', fn ($query) => $query->where('order_code', 'DEMO-ORDER-001'))->firstOrFail();
        $snapshotName = $item->product_name;
        $item->product->update(['name_vi' => 'Tên sản phẩm đã thay đổi']);
        $this->assertSame($snapshotName, $item->fresh()->product_name);

        $itemCount = OrderItem::count();
        $historyCount = OrderStatusHistory::count();
        $stockBefore = Product::where('sku', 'like', 'DEMO-%')->pluck('quantity', 'id')->all();
        $this->seed(OrderSeeder::class);

        $this->assertSame(48, Order::where('order_code', 'like', 'DEMO-ORDER-%')->count());
        $this->assertSame($itemCount, OrderItem::count());
        $this->assertSame($historyCount, OrderStatusHistory::count());
        $this->assertSame($stockBefore, Product::where('sku', 'like', 'DEMO-%')->pluck('quantity', 'id')->all());
    }
}
