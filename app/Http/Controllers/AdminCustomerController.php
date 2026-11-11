<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminCustomerIndexRequest;
use App\Http\Requests\AdminCustomerShowRequest;
use App\Http\Requests\UpdateCustomerStatusRequest;
use App\Models\Order;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminCustomerController extends Controller
{
    public function index(AdminCustomerIndexRequest $request): View
    {
        $filters = $request->validated();
        $customers = User::query()
            ->customers()
            ->searchCustomers($filters['q'] ?? null)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->withCount([
                'orders',
                'orders as delivered_orders_count' => fn ($query) => $query->where('status', 'delivered'),
                'orders as cancelled_orders_count' => fn ($query) => $query->where('status', 'cancelled'),
            ])
            ->withSum(
                ['orders as delivered_spending' => fn ($query) => $query->where('status', 'delivered')],
                'total_amount',
            )
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'filters' => $filters,
            'statuses' => User::STATUSES,
        ]);
    }

    public function show(
        AdminCustomerShowRequest $request,
        User $customer,
        MoneyFormatter $moneyFormatter,
    ): View
    {
        abort_unless($customer->isCustomer(), 404);

        $filters = $request->validated();

        $aggregates = $customer->orders()
            ->selectRaw('COUNT(*) as total_orders')
            ->selectRaw("SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END), 0) as delivered_spending")
            ->selectRaw("COALESCE(AVG(CASE WHEN status = 'delivered' THEN total_amount END), 0) as average_delivered_order")
            ->selectRaw("MAX(CASE WHEN status <> 'cancelled' THEN created_at END) as latest_purchase_at")
            ->first();

        $orderStats = [
            'total_orders' => (int) $aggregates->total_orders,
            'delivered_orders' => (int) $aggregates->delivered_orders,
            'cancelled_orders' => (int) $aggregates->cancelled_orders,
            'delivered_spending' => (float) $aggregates->delivered_spending,
            'average_delivered_order' => (float) $aggregates->average_delivered_order,
            'latest_purchase_at' => $aggregates->latest_purchase_at
                ? Carbon::parse($aggregates->latest_purchase_at)
                : null,
        ];

        $orders = $customer->orders()
            ->when(
                $filters['order_status'] ?? null,
                fn ($query, $status) => $query->where('status', $status),
            )
            ->latest('created_at')
            ->latest('id')
            ->paginate(10, ['*'], 'orders_page')
            ->withQueryString();

        $customerSegment = $customer->customerSegment($orderStats);

        return view('admin.customers.show', [
            'customer' => $customer,
            'orderStats' => $orderStats,
            'orders' => $orders,
            'orderFilters' => $filters,
            'orderStatuses' => Order::STATUSES,
            'customerSegment' => $customerSegment,
            'statuses' => User::STATUSES,
            'moneyFormatter' => $moneyFormatter,
        ]);
    }

    public function updateStatus(UpdateCustomerStatusRequest $request, User $customer): RedirectResponse
    {
        abort_if($request->user()->is($customer), 403, 'Bạn không thể thay đổi trạng thái tài khoản của chính mình.');
        abort_unless($customer->isCustomer(), 404);

        $status = $request->validated('status');
        $customer->update(['status' => $status]);

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', $status === 'blocked'
                ? 'Đã khóa tài khoản khách hàng.'
                : 'Đã mở lại tài khoản khách hàng.');
    }
}
