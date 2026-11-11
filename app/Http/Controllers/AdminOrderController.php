<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminOrderIndexRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Services\OrderStatusService;
use App\Support\MoneyFormatter;
use App\Support\SystemSettingPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminOrderController extends Controller
{
    public function index(AdminOrderIndexRequest $request, MoneyFormatter $moneyFormatter): View
    {
        $filters = $request->validated();
        $orders = Order::query()
            ->with('user')
            ->withCount('items')
            ->when($filters['q'] ?? null, function (Builder $query, string $search): void {
                $like = '%'.$search.'%';
                $query->where(function (Builder $query) use ($like): void {
                    $query->where('order_code', 'like', $like)
                        ->orWhere('recipient_name', 'like', $like)
                        ->orWhere('recipient_phone', 'like', $like)
                        ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'like', $like));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, string $method) => $query->where('payment_method', $method))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.orders.index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'moneyFormatter' => $moneyFormatter,
        ]);
    }

    public function show(Order $order, MoneyFormatter $moneyFormatter): View
    {
        $order->load(['user', 'items', 'statusHistories.changedBy']);
        $customer = $order->user && $order->user->isCustomer() ? $order->user : null;

        $user = request()->user();
        $availableTransitions = collect($order->allowedTransitions())->filter(
            fn (string $status) => $status === 'cancelled'
                ? $user->hasPermission('orders.cancel')
                : $user->hasPermission('orders.update'),
        )->values()->all();

        return view('admin.orders.show', [
            'order' => $order,
            'customer' => $customer,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'availableTransitions' => $availableTransitions,
            'moneyFormatter' => $moneyFormatter,
        ]);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order, OrderStatusService $service): RedirectResponse
    {
        $data = $request->validated();
        $permission = $data['status'] === 'cancelled' ? 'orders.cancel' : 'orders.update';
        abort_unless($request->user()->hasPermission($permission), 403);

        $service->transition(
            $order, $data['status'], $request->user(),
            $data['note'] ?? null, $data['cancel_reason'] ?? null,
        );

        return redirect()->route('admin.orders.show', $order)->with('success', 'Đã cập nhật trạng thái đơn hàng.');
    }

    public function printInvoice(
        Order $order,
        MoneyFormatter $moneyFormatter,
        SystemSettingPresenter $settingsPresenter,
    ): View
    {
        $order->load(['user', 'items']);

        return view('admin.orders.print', [
            'order' => $order,
            'statuses' => Order::STATUSES,
            'paymentStatuses' => Order::PAYMENT_STATUSES,
            'paymentMethods' => Order::PAYMENT_METHODS,
            'moneyFormatter' => $moneyFormatter,
            'invoiceSettings' => $settingsPresenter->invoice(app()->getLocale()),
        ]);
    }
}
