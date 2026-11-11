<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderWorkflowSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_workflow_constants_and_transitions_are_standardized(): void
    {
        $order = new Order(['status' => 'pending']);
        $this->assertSame(['confirmed', 'cancelled'], $order->allowedTransitions());
        $this->assertTrue($order->canTransitionTo('confirmed'));
        $this->assertFalse($order->canTransitionTo('shipped'));
        $order->status = 'shipped';
        $this->assertSame(['delivered'], $order->allowedTransitions());
        $this->assertFalse($order->canTransitionTo('cancelled'));
        $order->status = 'delivered';
        $this->assertSame([], $order->allowedTransitions());
    }

    public function test_order_history_relations_and_workflow_timestamps_are_available(): void
    {
        $customer = User::factory()->create(); $admin = User::factory()->create(); $order = $this->order($customer);
        $order->update(['confirmed_at' => now(), 'stock_deducted_at' => now()]);
        $history = $order->statusHistories()->create(['from_status' => 'pending', 'to_status' => 'confirmed', 'changed_by' => $admin->id, 'note' => 'Xác nhận kiểm thử', 'created_at' => now()]);
        $this->assertTrue($history->order->is($order));
        $this->assertTrue($history->changedBy->is($admin));
        $this->assertTrue($admin->orderStatusChanges->first()->is($history));
        $this->assertNotNull($order->fresh()->confirmed_at);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
    }

    public function test_deleting_actor_keeps_history_and_deleting_order_cascades_history(): void
    {
        $actor = User::factory()->create(); $order = $this->order();
        $history = OrderStatusHistory::create(['order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending', 'changed_by' => $actor->id, 'created_at' => now()]);
        $actor->delete();
        $this->assertDatabaseHas('order_status_histories', ['id' => $history->id, 'changed_by' => null]);
        $order->delete();
        $this->assertDatabaseMissing('order_status_histories', ['id' => $history->id]);
    }

    public function test_workflow_migration_preserves_existing_orders_and_backfills_initial_history(): void
    {
        $order = $this->order();
        $migration = require database_path('migrations/2026_10_29_000010_add_workflow_fields_to_orders_table.php');

        $migration->down();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_code' => $order->order_code]);

        $migration->up();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'order_code' => $order->order_code]);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending', 'changed_by' => null,
        ]);
    }

    private function order(?User $customer = null): Order
    {
        return Order::create([
            'order_code' => 'WORKFLOW-'.fake()->unique()->numerify('#####'), 'user_id' => $customer?->id,
            'status' => 'pending', 'payment_method' => 'cod', 'payment_status' => 'unpaid',
            'subtotal' => 100000, 'shipping_fee' => 20000, 'total_amount' => 120000,
            'recipient_name' => 'Khách kiểm thử', 'recipient_phone' => '0900000000', 'shipping_address' => 'Địa chỉ kiểm thử',
        ]);
    }
}
