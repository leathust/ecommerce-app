<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = $this->userWithRole('order_manager');
        $this->actingAs($this->manager);
        $category = Category::create(['name_vi' => 'Kho', 'name_en' => 'Inventory', 'slug' => 'inventory-status-test']);
        $this->product = Product::create([
            'category_id' => $category->id, 'sku' => 'INVENTORY-STATUS', 'name_vi' => 'Sản phẩm trạng thái',
            'name_en' => 'Status product', 'slug' => 'inventory-status-product', 'price' => 100000, 'quantity' => 100,
        ]);
    }

    public function test_valid_transition_sets_timestamp_and_records_actor_history(): void
    {
        $order = $this->order();

        $this->patch(route('admin.orders.status.update', $order), [
            'status' => 'confirmed', 'note' => 'Đã gọi xác nhận khách hàng',
        ])->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertNotNull($order->confirmed_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => 'pending', 'to_status' => 'confirmed',
            'changed_by' => $this->manager->id, 'note' => 'Đã gọi xác nhận khách hàng',
        ]);
    }

    public function test_invalid_or_repeated_transition_is_rejected_without_partial_changes(): void
    {
        $order = $this->order();

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'shipped'])
            ->assertSessionHasErrors('status');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'shipped_at' => null]);
        $this->assertDatabaseCount('order_status_histories', 0);

        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_order_can_follow_complete_forward_workflow_and_terminal_order_has_no_actions(): void
    {
        $order = $this->order();
        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->patch(route('admin.orders.status.update', $order), ['status' => $status])->assertSessionHasNoErrors();
        }

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        foreach (['confirmed_at', 'processing_at', 'shipped_at', 'delivered_at'] as $field) {
            $this->assertNotNull($order->{$field});
        }
        $this->assertSame(4, $order->statusHistories()->count());
        $this->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('không còn bước chuyển tiếp')->assertDontSee('id="order-status-form"', false);
    }

    public function test_cancellation_requires_reason_and_records_it(): void
    {
        $order = $this->order();
        $this->patch(route('admin.orders.status.update', $order), ['status' => 'cancelled'])
            ->assertSessionHasErrors('cancel_reason');
        $this->assertSame('pending', $order->fresh()->status);

        $this->patch(route('admin.orders.status.update', $order), [
            'status' => 'cancelled', 'cancel_reason' => 'Khách yêu cầu hủy đơn.',
        ])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Khách yêu cầu hủy đơn.', $order->cancel_reason);
        $this->assertNotNull($order->cancelled_at);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'to_status' => 'cancelled', 'note' => 'Khách yêu cầu hủy đơn.',
        ]);
    }

    public function test_update_and_cancel_permissions_are_enforced_separately(): void
    {
        $role = Role::where('name', 'order_manager')->firstOrFail();
        $viewId = Permission::where('name', 'orders.view')->value('id');
        $updateId = Permission::where('name', 'orders.update')->value('id');
        $cancelId = Permission::where('name', 'orders.cancel')->value('id');

        $role->permissions()->sync([$viewId, $updateId]);
        $updateOnlyOrder = $this->order('UPDATE-ONLY');
        $this->patch(route('admin.orders.status.update', $updateOnlyOrder), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.status.update', $updateOnlyOrder), ['status' => 'cancelled', 'cancel_reason' => 'Không có quyền'])->assertForbidden();

        $role->permissions()->sync([$viewId, $cancelId]);
        $cancelOnlyOrder = $this->order('CANCEL-ONLY');
        $this->get(route('admin.orders.show', $cancelOnlyOrder))
            ->assertViewHas('availableTransitions', fn ($transitions) => $transitions === ['cancelled']);
        $this->patch(route('admin.orders.status.update', $cancelOnlyOrder), ['status' => 'confirmed'])->assertForbidden();
        $this->patch(route('admin.orders.status.update', $cancelOnlyOrder), ['status' => 'cancelled', 'cancel_reason' => 'Hủy hợp lệ'])->assertSessionHasNoErrors();
    }

    private function order(?string $code = null): Order
    {
        $order = Order::create([
            'order_code' => $code ?? 'STATUS-'.fake()->unique()->numerify('#####'),
            'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'subtotal' => 100000, 'shipping_fee' => 30000, 'total_amount' => 130000,
            'recipient_name' => 'Khách trạng thái', 'recipient_phone' => '0900000000', 'shipping_address' => 'Địa chỉ kiểm thử',
        ]);
        $order->items()->create([
            'product_id' => $this->product->id, 'product_name' => $this->product->name_vi, 'sku' => $this->product->sku,
            'quantity' => 1, 'price' => 100000, 'total_price' => 100000,
        ]);
        return $order;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        return $user;
    }
}
