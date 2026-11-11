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

class AdminOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->manager = $this->userWithRole('order_manager');
        $this->actingAs($this->manager);
    }

    public function test_order_list_is_paginated_keeps_filters_and_distinguishes_guest_orders(): void
    {
        $customer = User::factory()->create(['email' => 'registered@example.com']);
        for ($number = 1; $number <= 16; $number++) {
            $order = $this->order($number, 'pending', $number === 16 ? null : $customer);
            $order->items()->create(['product_id' => null, 'product_name' => 'Snapshot', 'sku' => 'SNAP-'.$number, 'quantity' => 1, 'price' => 100000, 'total_price' => 100000]);
        }

        $this->get(route('admin.orders', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Khách vãng lai')
            ->assertSee('registered@example.com')
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 16
                && $orders->count() === 15
                && str_contains($orders->url(2), 'status=pending'));
    }

    public function test_orders_can_be_searched_by_code_recipient_phone_and_customer_email(): void
    {
        $customer = User::factory()->create(['email' => 'buyer-search@example.com']);
        $matched = $this->order(1, 'confirmed', $customer, [
            'order_code' => 'SPECIAL-ORDER', 'recipient_name' => 'Nguyễn Người Nhận', 'recipient_phone' => '0911222333',
        ]);
        $this->order(2, 'pending');

        foreach (['SPECIAL-ORDER', 'Người Nhận', '0911222333', 'buyer-search@example.com'] as $search) {
            $this->get(route('admin.orders', ['q' => $search]))
                ->assertOk()
                ->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->is($matched));
        }
    }

    public function test_orders_can_be_filtered_by_workflow_payment_and_date_range(): void
    {
        $matched = $this->order(1, 'delivered', null, [
            'payment_status' => 'paid', 'payment_method' => 'card', 'created_at' => '2026-10-15 10:00:00',
        ]);
        $this->order(2, 'delivered', null, ['payment_status' => 'unpaid', 'payment_method' => 'cod', 'created_at' => '2026-10-20 10:00:00']);
        $this->order(3, 'pending', null, ['payment_status' => 'paid', 'payment_method' => 'card', 'created_at' => '2026-10-15 10:00:00']);

        $this->get(route('admin.orders', [
            'status' => 'delivered', 'payment_status' => 'paid', 'payment_method' => 'card',
            'date_from' => '2026-10-15', 'date_to' => '2026-10-15',
        ]))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->total() === 1 && $orders->first()->is($matched));
    }

    public function test_order_filters_are_validated_and_list_requires_permission(): void
    {
        $this->get(route('admin.orders', [
            'status' => 'unknown', 'payment_status' => 'unknown', 'payment_method' => 'unknown',
            'date_from' => '2026-10-20', 'date_to' => '2026-10-10',
        ]))->assertSessionHasErrors(['status', 'payment_status', 'payment_method', 'date_from', 'date_to']);

        $this->actingAs($this->userWithRole('catalog_manager'))
            ->get(route('admin.orders'))
            ->assertForbidden();
    }

    public function test_order_detail_shows_snapshot_totals_customer_link_and_history(): void
    {
        $customer = User::factory()->create(['first_name' => 'Mai', 'last_name' => 'Trần', 'email' => 'mai@example.com']);
        $category = Category::create(['name_vi' => 'Danh mục', 'name_en' => 'Category', 'slug' => 'order-detail-category']);
        $product = Product::create([
            'category_id' => $category->id, 'sku' => 'CURRENT-SKU', 'name_vi' => 'Tên sản phẩm hiện tại',
            'name_en' => 'Current product', 'slug' => 'current-product', 'price' => 250000, 'quantity' => 5,
        ]);
        $order = $this->order(50, 'confirmed', $customer, [
            'order_code' => 'DETAIL-ORDER', 'payment_status' => 'paid', 'payment_method' => 'card',
            'subtotal' => 400000, 'shipping_fee' => 30000, 'total_amount' => 430000,
        ]);
        $item = $order->items()->create([
            'product_id' => $product->id, 'product_name' => 'Tên snapshot', 'sku' => 'SNAPSHOT-SKU',
            'quantity' => 2, 'price' => 200000, 'total_price' => 400000,
        ]);
        $order->statusHistories()->create([
            'from_status' => 'pending', 'to_status' => 'confirmed', 'changed_by' => $this->manager->id,
            'note' => 'Đã xác nhận với khách', 'created_at' => now(),
        ]);
        $product->delete();

        $this->get(route('admin.orders'))
            ->assertOk()->assertSee(route('admin.orders.show', $order), false);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('DETAIL-ORDER')
            ->assertSee('Tên snapshot')
            ->assertSee('SNAPSHOT-SKU')
            ->assertSee('430.000 ₫')
            ->assertSee('Sản phẩm gốc không còn tồn tại')
            ->assertSee(route('admin.customers.show', $customer), false)
            ->assertSee('Đã xác nhận với khách')
            ->assertSee($this->manager->display_name)
            ->assertSee('data-bs-target="#order-status-modal"', false)
            ->assertSee('id="order-status-modal"', false)
            ->assertSee('Cập nhật trạng thái đơn hàng')
            ->assertDontSee('order-status-panel', false)
            ->assertViewHas('order', fn (Order $viewOrder) => $viewOrder->is($order)
                && $viewOrder->items->first()->is($item)
                && $viewOrder->statusHistories->count() === 1);
    }

    public function test_guest_order_detail_has_no_customer_link(): void
    {
        $order = $this->order(60, 'pending');

        $this->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Đơn hàng của khách vãng lai')
            ->assertDontSee('Xem hồ sơ khách hàng');
    }

    public function test_order_detail_requires_permission_and_unknown_order_returns_not_found(): void
    {
        $order = $this->order(70, 'pending');
        $this->actingAs($this->userWithRole('catalog_manager'))
            ->get(route('admin.orders.show', $order))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get('/admin/orders/999999')
            ->assertNotFound();
    }

    public function test_printable_invoice_uses_snapshot_and_has_no_admin_layout(): void
    {
        $customer = User::factory()->create(['email' => 'invoice-customer@example.com']);
        $order = $this->order(80, 'delivered', $customer, [
            'order_code' => 'INVOICE-ORDER', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer',
            'subtotal' => 500000, 'shipping_fee' => 30000, 'total_amount' => 530000,
        ]);
        $order->items()->create([
            'product_id' => null, 'product_name' => 'Sản phẩm hóa đơn snapshot', 'sku' => 'INV-SNAPSHOT',
            'quantity' => 2, 'price' => 250000, 'total_price' => 500000,
        ]);

        $this->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee(route('admin.orders.print', $order), false);

        $this->get(route('admin.orders.print', $order))
            ->assertOk()
            ->assertSee('INVOICE-ORDER')
            ->assertSee('Sản phẩm hóa đơn snapshot')
            ->assertSee('INV-SNAPSHOT')
            ->assertSee('530.000 ₫')
            ->assertSee('invoice-customer@example.com')
            ->assertSee('window.print()', false)
            ->assertDontSee('class="page-wrapper"', false)
            ->assertDontSee('admin.includes.sidebar', false);
    }

    public function test_printable_invoice_requires_permission_and_unknown_order_returns_not_found(): void
    {
        $order = $this->order(90, 'pending');
        $this->actingAs($this->userWithRole('catalog_manager'))
            ->get(route('admin.orders.print', $order))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get('/admin/orders/999999/print')
            ->assertNotFound();
    }

    private function order(int $number, string $status, ?User $customer = null, array $overrides = []): Order
    {
        $attributes = array_merge([
            'order_code' => 'LIST-'.str_pad((string)$number, 3, '0', STR_PAD_LEFT),
            'user_id' => $customer?->id, 'status' => $status, 'payment_method' => 'cod',
            'payment_status' => 'unpaid', 'subtotal' => 100000, 'shipping_fee' => 30000,
            'total_amount' => 130000, 'recipient_name' => 'Khách '.$number,
            'recipient_phone' => '090'.str_pad((string)$number, 7, '0', STR_PAD_LEFT),
            'shipping_address' => 'Địa chỉ '.$number,
        ], $overrides);

        $createdAt = $attributes['created_at'] ?? null;
        unset($attributes['created_at']);
        $order = Order::create($attributes);
        if ($createdAt) { $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly(); }

        return $order;
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        return $user;
    }
}
