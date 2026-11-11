<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());
        $this->actingAs($this->manager);
        $category = Category::create(['name_vi' => 'Tồn kho', 'name_en' => 'Inventory', 'slug' => 'inventory-test']);
        $this->product = Product::create([
            'category_id' => $category->id, 'sku' => 'STOCK-001', 'name_vi' => 'Sản phẩm tồn kho',
            'name_en' => 'Stock product', 'slug' => 'stock-product', 'price' => 100000, 'quantity' => 10,
        ]);
    }

    public function test_confirming_order_deducts_grouped_stock_once(): void
    {
        $order = $this->order([2, 3]);
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();

        $this->assertSame(5, $this->product->fresh()->quantity);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertNull($order->fresh()->stock_restored_at);
        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_insufficient_stock_rolls_back_products_status_timestamp_and_history(): void
    {
        $order = $this->order([6, 5]);
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(10, $this->product->fresh()->quantity);
        $order->refresh();
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->confirmed_at);
        $this->assertNull($order->stock_deducted_at);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public function test_cancelling_confirmed_order_restores_stock_exactly_once(): void
    {
        $order = $this->order([4]);
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame(6, $this->product->fresh()->quantity);

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled', 'cancel_reason' => 'Khách hủy'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, $this->product->fresh()->quantity);
        $this->assertNotNull($order->fresh()->stock_restored_at);

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled', 'cancel_reason' => 'Gửi lặp'])
            ->assertSessionHasErrors('status');
        $this->assertSame(10, $this->product->fresh()->quantity);
    }

    public function test_cancelling_pending_order_does_not_restore_stock(): void
    {
        $order = $this->order([3]);
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled', 'cancel_reason' => 'Hủy sớm'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, $this->product->fresh()->quantity);
        $this->assertNull($order->fresh()->stock_deducted_at);
        $this->assertNull($order->fresh()->stock_restored_at);
    }

    public function test_legacy_confirmed_order_deducts_on_next_forward_transition(): void
    {
        $order = $this->order([3], 'confirmed');
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->assertSame(7, $this->product->fresh()->quantity);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
    }

    public function test_missing_product_blocks_confirmation_but_can_be_skipped_when_restoring(): void
    {
        $pending = $this->order([1]);
        $this->product->delete();
        $this->patch(route('admin.orders.status.update', $pending), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $pending->fresh()->status);

        $replacement = Product::create([
            'category_id' => Category::first()->id, 'sku' => 'STOCK-002', 'name_vi' => 'Sản phẩm thay thế',
            'name_en' => 'Replacement', 'slug' => 'replacement-product', 'price' => 100000, 'quantity' => 5,
        ]);
        $confirmed = $this->order([2], 'confirmed', $replacement);
        $confirmed->update(['stock_deducted_at' => now()]);
        $replacement->delete();
        $this->patch(route('admin.orders.status.update', $confirmed), ['status' => 'cancelled', 'cancel_reason' => 'Sản phẩm đã xóa'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($confirmed->fresh()->stock_restored_at);
    }

    public function test_empty_or_non_positive_item_quantity_blocks_confirmation(): void
    {
        $emptyOrder = $this->order([]);
        $this->patch(route('admin.orders.status.update', $emptyOrder), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');

        $invalidOrder = $this->order([0]);
        $this->patch(route('admin.orders.status.update', $invalidOrder), ['status' => 'confirmed'])
            ->assertSessionHasErrors('status');

        $this->assertSame(10, $this->product->fresh()->quantity);
        $this->assertSame('pending', $emptyOrder->fresh()->status);
        $this->assertSame('pending', $invalidOrder->fresh()->status);
    }

    private function order(array $quantities, string $status = 'pending', ?Product $product = null): Order
    {
        $product ??= $this->product;
        $order = Order::create([
            'order_code' => 'STOCK-'.fake()->unique()->numerify('#####'), 'status' => $status,
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'subtotal' => 100000,
            'shipping_fee' => 0, 'total_amount' => 100000, 'recipient_name' => 'Khách tồn kho',
            'recipient_phone' => '0900000000', 'shipping_address' => 'Địa chỉ kiểm thử',
        ]);
        foreach ($quantities as $index => $quantity) {
            $order->items()->create([
                'product_id' => $product->id, 'product_name' => $product->name_vi, 'sku' => $product->sku,
                'quantity' => $quantity, 'price' => 100000, 'total_price' => 100000 * $quantity,
            ]);
        }
        return $order;
    }
}
