<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCustomerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = $this->userWithRole('admin');
        $this->actingAs($this->admin);
    }

    public function test_customer_list_is_paginated_and_excludes_administrative_accounts(): void
    {
        User::factory()->count(16)->create();
        $otherAdmin = $this->userWithRole('catalog_manager');

        $this->get(route('admin.customers'))
            ->assertOk()
            ->assertViewHas('customers', function ($customers) use ($otherAdmin): bool {
                return $customers->total() === 16
                    && $customers->count() === 15
                    && ! $customers->pluck('id')->contains($this->admin->id)
                    && ! $customers->pluck('id')->contains($otherAdmin->id);
            });
    }

    public function test_customers_can_be_searched_and_filtered_by_status(): void
    {
        $matched = User::factory()->create([
            'name' => 'Tên dự phòng', 'first_name' => 'An', 'last_name' => 'Nguyễn',
            'username' => 'an.nguyen', 'email' => 'an@example.com', 'phone' => '0901234567', 'status' => 'blocked',
        ]);
        User::factory()->create(['first_name' => 'An', 'last_name' => 'Khác', 'status' => 'active']);

        $this->get(route('admin.customers', ['q' => 'An Nguyễn', 'status' => 'blocked']))
            ->assertOk()
            ->assertSee('An Nguyễn')
            ->assertSee('Đã khóa')
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1 && $customers->first()->is($matched));

        $this->get(route('admin.customers', ['q' => '0901234567']))
            ->assertViewHas('customers', fn ($customers) => $customers->total() === 1 && $customers->first()->is($matched));
    }

    public function test_non_admin_role_is_still_treated_as_a_customer(): void
    {
        $memberRole = Role::create(['name' => 'loyalty_member', 'description' => 'Thành viên']);
        $customer = User::factory()->create();
        $customer->roles()->attach($memberRole);

        $this->get(route('admin.customers'))
            ->assertViewHas('customers', fn ($customers) => $customers->pluck('id')->contains($customer->id));
    }

    public function test_customers_are_grouped_dynamically_from_order_aggregates(): void
    {
        $newCustomer = User::factory()->create();
        $returningCustomer = User::factory()->create();
        $loyalCustomer = User::factory()->create();
        $cancellationRiskCustomer = User::factory()->create();

        foreach (range(1, 2) as $number) {
            $this->order($returningCustomer, 'delivered', 'RETURNING-'.$number);
        }
        foreach (range(1, 5) as $number) {
            $this->order($loyalCustomer, 'delivered', 'LOYAL-'.$number);
        }
        foreach (range(1, 3) as $number) {
            $this->order($cancellationRiskCustomer, 'cancelled', 'RISK-'.$number);
        }

        $this->get(route('admin.customers'))
            ->assertOk()
            ->assertSee('Khách mới')
            ->assertSee('Khách quay lại')
            ->assertSee('Khách thân thiết')
            ->assertSee('Nhiều đơn bị hủy')
            ->assertViewHas('customers', function ($customers) use (
                $newCustomer,
                $returningCustomer,
                $loyalCustomer,
                $cancellationRiskCustomer,
            ): bool {
                $segments = $customers->getCollection()->mapWithKeys(
                    fn (User $customer) => [$customer->id => $customer->customerSegment()['key']],
                );

                return $segments[$newCustomer->id] === 'new'
                    && $segments[$returningCustomer->id] === 'returning'
                    && $segments[$loyalCustomer->id] === 'loyal'
                    && $segments[$cancellationRiskCustomer->id] === 'cancellation_risk';
            });
    }

    public function test_customer_segment_prioritizes_cancellation_risk_and_can_use_spending(): void
    {
        $customer = User::factory()->create();

        $this->assertSame('loyal', $customer->customerSegment([
            'total_orders' => 1,
            'delivered_orders' => 1,
            'cancelled_orders' => 0,
            'delivered_spending' => 5000000,
        ])['key']);

        $this->assertSame('cancellation_risk', $customer->customerSegment([
            'total_orders' => 6,
            'delivered_orders' => 3,
            'cancelled_orders' => 3,
            'delivered_spending' => 6000000,
        ])['key']);
    }

    public function test_customer_list_requires_customers_view_permission_and_valid_filters(): void
    {
        $catalogManager = $this->userWithRole('catalog_manager');
        $this->actingAs($catalogManager)->get(route('admin.customers'))->assertForbidden();

        $orderManager = $this->userWithRole('order_manager');
        $this->actingAs($orderManager)->get(route('admin.customers'))->assertOk();
        $this->actingAs($orderManager)->get(route('admin.customers', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');
    }

    public function test_customer_detail_shows_profile_dates_status_and_order_summary(): void
    {
        $customer = User::factory()->create([
            'name' => 'Tên hệ thống', 'first_name' => 'Minh', 'last_name' => 'Trần',
            'phone' => '0912345678', 'status' => 'active',
        ]);
        $this->order($customer, 'pending', 'DETAIL-001', 80000, '2026-10-29 10:00:00');
        $this->order($customer, 'delivered', 'DETAIL-002', 120000, '2026-10-27 10:00:00');
        $this->order($customer, 'delivered', 'DETAIL-003', 240000, '2026-10-28 10:00:00');
        $this->order($customer, 'cancelled', 'DETAIL-004', 999000, '2026-10-29 11:00:00');

        $this->get(route('admin.customers'))
            ->assertOk()
            ->assertSee(route('admin.customers.show', $customer), false);

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Minh Trần')
            ->assertSee('0912345678')
            ->assertSee('Hoạt động')
            ->assertSee('Khách quay lại')
            ->assertSee('360.000 ₫')
            ->assertSee('180.000 ₫')
            ->assertSee('29/10/2026 10:00')
            ->assertViewHas('customer', fn (User $viewCustomer) => $viewCustomer->is($customer))
            ->assertViewHas('customerSegment', fn (array $segment) => $segment['key'] === 'returning')
            ->assertViewHas('orderStats', fn (array $stats) => $stats['total_orders'] === 4
                && $stats['delivered_orders'] === 2
                && $stats['cancelled_orders'] === 1
                && $stats['delivered_spending'] === 360000.0
                && $stats['average_delivered_order'] === 180000.0
                && $stats['latest_purchase_at']->format('Y-m-d H:i:s') === '2026-10-29 10:00:00');
    }

    public function test_customer_order_statistics_have_safe_defaults_without_orders(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Chưa có đơn hàng')
            ->assertViewHas('orderStats', fn (array $stats) => $stats['total_orders'] === 0
                && $stats['delivered_orders'] === 0
                && $stats['cancelled_orders'] === 0
                && $stats['delivered_spending'] === 0.0
                && $stats['average_delivered_order'] === 0.0
                && $stats['latest_purchase_at'] === null);
    }

    public function test_customer_purchase_history_is_paginated_filtered_and_links_to_order_detail(): void
    {
        $customer = User::factory()->create();
        $orders = collect();

        foreach (range(1, 12) as $number) {
            $status = $number <= 3 ? 'cancelled' : 'delivered';
            $orders->push($this->order(
                $customer,
                $status,
                sprintf('HISTORY-%03d', $number),
                100000 + ($number * 1000),
                sprintf('2026-10-%02d 10:00:00', $number),
            ));
        }

        $this->get(route('admin.customers.show', $customer))
            ->assertOk()
            ->assertSee('Lịch sử mua hàng')
            ->assertSee(route('admin.orders.show', $orders->last()), false)
            ->assertViewHas('orders', fn ($paginator) => $paginator->total() === 12
                && $paginator->count() === 10
                && $paginator->getPageName() === 'orders_page');

        $this->get(route('admin.customers.show', [
            'customer' => $customer,
            'order_status' => 'cancelled',
        ]))
            ->assertOk()
            ->assertSee('HISTORY-001')
            ->assertDontSee('HISTORY-012')
            ->assertViewHas('orders', fn ($paginator) => $paginator->total() === 3
                && $paginator->every(fn (Order $order) => $order->status === 'cancelled'));

        $this->get(route('admin.customers.show', [
            'customer' => $customer,
            'orders_page' => 2,
        ]))->assertViewHas('orders', fn ($paginator) => $paginator->currentPage() === 2
            && $paginator->count() === 2);
    }

    public function test_customer_purchase_history_rejects_invalid_status_filter(): void
    {
        $customer = User::factory()->create();

        $this->get(route('admin.customers.show', [
            'customer' => $customer,
            'order_status' => 'unknown',
        ]))->assertSessionHasErrors('order_status');
    }

    public function test_customer_detail_returns_not_found_for_admin_or_unknown_user(): void
    {
        $otherAdmin = $this->userWithRole('customer_support');

        $this->get(route('admin.customers.show', $otherAdmin))->assertNotFound();
        $this->get('/admin/customers/999999')->assertNotFound();
    }

    public function test_customer_detail_requires_customers_view_permission(): void
    {
        $customer = User::factory()->create();
        $catalogManager = $this->userWithRole('catalog_manager');

        $this->actingAs($catalogManager)
            ->get(route('admin.customers.show', $customer))
            ->assertForbidden();
    }

    public function test_admin_can_block_and_reopen_customer_account(): void
    {
        $customer = User::factory()->create(['status' => 'active']);

        $this->patch(route('admin.customers.status.update', $customer), ['status' => 'blocked'])
            ->assertRedirect(route('admin.customers.show', $customer))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'blocked']);

        $this->actingAs($customer->fresh())->get(route('admin.dashboard'))->assertRedirect(url('/admin'));
        $this->assertGuest();

        $this->actingAs($this->admin)
            ->patch(route('admin.customers.status.update', $customer), ['status' => 'active'])
            ->assertRedirect(route('admin.customers.show', $customer));
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'active']);
    }

    public function test_inactive_customer_can_be_reopened_but_invalid_status_is_rejected(): void
    {
        $customer = User::factory()->create(['status' => 'inactive']);

        $this->patch(route('admin.customers.status.update', $customer), ['status' => 'active'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'status' => 'active']);

        $this->patch(route('admin.customers.status.update', $customer), ['status' => 'inactive'])
            ->assertSessionHasErrors('status');
    }

    public function test_status_update_requires_permission_and_rejects_administrative_targets(): void
    {
        $customer = User::factory()->create();
        $orderManager = $this->userWithRole('order_manager');
        $this->actingAs($orderManager)
            ->patch(route('admin.customers.status.update', $customer), ['status' => 'blocked'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->patch(route('admin.customers.status.update', $this->admin), ['status' => 'blocked'])
            ->assertForbidden();

        $otherAdmin = $this->userWithRole('customer_support');
        $this->actingAs($this->admin)
            ->patch(route('admin.customers.status.update', $otherAdmin), ['status' => 'blocked'])
            ->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'status' => 'active']);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    private function order(
        User $customer,
        string $status,
        string $code,
        int $totalAmount = 120000,
        ?string $createdAt = null,
    ): Order
    {
        $order = Order::create([
            'order_code' => $code,
            'user_id' => $customer->id,
            'status' => $status,
            'payment_method' => 'cod',
            'payment_status' => $status === 'delivered' ? 'paid' : 'unpaid',
            'subtotal' => $totalAmount - 20000,
            'shipping_fee' => 20000,
            'total_amount' => $totalAmount,
            'recipient_name' => $customer->display_name,
            'recipient_phone' => $customer->phone ?? '0900000000',
            'shipping_address' => 'Địa chỉ kiểm thử',
        ]);

        if ($createdAt) {
            $order->timestamps = false;
            $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        }

        return $order;
    }
}
