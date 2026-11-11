<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($this->admin);
    }

    public function test_customer_statistics_only_use_the_intended_order_statuses(): void
    {
        $customer = User::factory()->create();
        $this->order($customer, 'pending', 'STAT-PENDING', 80000, '2026-10-26 10:00:00');
        $this->order($customer, 'delivered', 'STAT-DELIVERED-1', 120000, '2026-10-27 10:00:00');
        $this->order($customer, 'delivered', 'STAT-DELIVERED-2', 240000, '2026-10-28 10:00:00');
        $this->order($customer, 'cancelled', 'STAT-CANCELLED', 999000, '2026-10-29 10:00:00');

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertViewHas('orderStats', fn (array $stats) => $stats['total_orders'] === 4
                && $stats['delivered_orders'] === 2
                && $stats['cancelled_orders'] === 1
                && $stats['delivered_spending'] === 360000.0
                && $stats['average_delivered_order'] === 180000.0
                && $stats['latest_purchase_at']->format('Y-m-d H:i:s') === '2026-10-28 10:00:00');
    }

    public function test_guest_orders_are_not_assigned_to_a_registered_customer(): void
    {
        $customer = User::factory()->create();
        $registeredOrder = $this->order($customer, 'delivered', 'REGISTERED-ORDER');
        $guestOrder = $this->order(null, 'delivered', 'GUEST-ORDER');

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee($registeredOrder->order_code)
            ->assertDontSee($guestOrder->order_code)
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1
                && $orders->first()->is($registeredOrder));

        $this->assertNull($guestOrder->fresh()->user_id);
    }

    public function test_deleting_customer_keeps_order_history_and_nulls_user_reference(): void
    {
        $customer = User::factory()->create();
        $order = $this->order($customer, 'delivered', 'PRESERVED-ORDER');

        $customer->delete();

        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'user_id' => null,
            'order_code' => 'PRESERVED-ORDER',
            'recipient_name' => 'Snapshot Customer',
        ]);
        $this->assertNull($order->fresh()->user);
    }

    public function test_order_snapshot_does_not_change_when_customer_profile_changes(): void
    {
        $customer = User::factory()->create([
            'name' => 'Old Account Name',
            'email' => 'old-profile@example.test',
            'phone' => '0901000000',
        ]);
        $order = $this->order($customer, 'delivered', 'IMMUTABLE-SNAPSHOT');

        $customer->update([
            'name' => 'New Account Name',
            'email' => 'new-profile@example.test',
            'phone' => '0999999999',
        ]);

        $order->refresh();
        $this->assertSame('Snapshot Customer', $order->recipient_name);
        $this->assertSame('0900000000', $order->recipient_phone);
        $this->assertSame('Snapshot Address', $order->shipping_address);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Snapshot Customer')
            ->assertSee('0900000000')
            ->assertSee('Snapshot Address');
    }

    private function order(
        ?User $customer,
        string $status,
        string $code,
        int $totalAmount = 120000,
        ?string $createdAt = null,
    ): Order {
        $order = Order::create([
            'order_code' => $code,
            'user_id' => $customer?->id,
            'status' => $status,
            'payment_method' => 'cod',
            'payment_status' => $status === 'delivered' ? 'paid' : 'unpaid',
            'subtotal' => $totalAmount - 20000,
            'shipping_fee' => 20000,
            'total_amount' => $totalAmount,
            'recipient_name' => 'Snapshot Customer',
            'recipient_phone' => '0900000000',
            'shipping_address' => 'Snapshot Address',
        ]);

        if ($createdAt) {
            $order->timestamps = false;
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $order;
    }
}
