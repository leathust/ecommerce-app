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

class OrderModuleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_admin_flow_from_list_to_cancellation_and_invoice(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('name', 'order_manager')->firstOrFail());
        $this->actingAs($manager);
        $customer = User::factory()->create(['email' => 'integration-customer@example.com']);
        $category = Category::create(['name_vi' => 'Tích hợp', 'name_en' => 'Integration', 'slug' => 'order-integration']);
        $product = Product::create([
            'category_id' => $category->id, 'sku' => 'INTEGRATION-SKU', 'name_vi' => 'Sản phẩm tích hợp',
            'name_en' => 'Integration product', 'slug' => 'integration-product', 'price' => 200000, 'quantity' => 10,
        ]);
        $order = Order::create([
            'order_code' => 'INTEGRATION-ORDER', 'user_id' => $customer->id, 'status' => 'pending',
            'payment_method' => 'cod', 'payment_status' => 'unpaid', 'subtotal' => 600000,
            'shipping_fee' => 30000, 'total_amount' => 630000, 'recipient_name' => 'Khách tích hợp',
            'recipient_phone' => '0901234567', 'shipping_address' => 'Địa chỉ tích hợp',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Snapshot tích hợp', 'sku' => 'SNAPSHOT-INTEGRATION',
            'quantity' => 3, 'price' => 200000, 'total_price' => 600000,
        ]);

        $this->get(route('admin.orders', ['q' => 'INTEGRATION-ORDER']))
            ->assertOk()->assertSee('INTEGRATION-ORDER')->assertSee('integration-customer@example.com');
        $this->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Snapshot tích hợp')->assertSee('630.000 ₫');

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed', 'note' => 'Xác nhận tích hợp'])
            ->assertSessionHasNoErrors();
        $this->assertSame(7, $product->fresh()->quantity);
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'processing'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.status.update', $order), [
            'status' => 'cancelled', 'cancel_reason' => 'Khách đổi nhu cầu',
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame(10, $product->fresh()->quantity);
        $this->assertNotNull($order->stock_deducted_at);
        $this->assertNotNull($order->stock_restored_at);
        $this->assertSame(['confirmed', 'processing', 'cancelled'], $order->statusHistories()->pluck('to_status')->all());

        $this->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Khách đổi nhu cầu')->assertSee('Xác nhận tích hợp');
        $this->get(route('admin.orders.print', $order))
            ->assertOk()->assertSee('Snapshot tích hợp')->assertSee('630.000 ₫')->assertSee('Đã hủy');
    }
}
