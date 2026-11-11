@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Đơn hàng</h1><span class="badge text-bg-primary">{{ number_format($orders->total()) }} đơn hàng</span></div><p class="text-muted mb-0">Theo dõi và tra cứu các đơn hàng trong hệ thống.</p></div>
  </div>

  <section class="m-card mb-4" aria-labelledby="order-filter-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="order-filter-title">Tìm kiếm và lọc</h2><p class="m-card__subtitle">Có thể kết hợp nhiều điều kiện để thu hẹp kết quả.</p></div></header>
    <form action="{{ route('admin.orders') }}" method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-xl-4"><label class="form-label" for="order-search">Tìm kiếm</label><input class="form-control" id="order-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Mã đơn, người nhận, điện thoại, email..."></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="order-status">Trạng thái đơn</label><select class="form-select" id="order-status" name="status"><option value="">Tất cả</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="payment-status">Thanh toán</label><select class="form-select" id="payment-status" name="payment_status"><option value="">Tất cả</option>@foreach($paymentStatuses as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="payment-method">Phương thức</label><select class="form-select" id="payment-method" name="payment_method"><option value="">Tất cả</option>@foreach($paymentMethods as $value => $label)<option value="{{ $value }}" @selected(($filters['payment_method'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="date-from">Từ ngày</label><input class="form-control" id="date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="date-to">Đến ngày</label><input class="form-control" id="date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
      <div class="col-12 col-md-4 col-xl-3 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Áp dụng</button><a class="btn btn-outline-secondary" href="{{ route('admin.orders') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Xóa lọc</a></div>
    </form>
  </section>

  <section class="m-card" aria-labelledby="order-list-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="order-list-title">Danh sách đơn hàng</h2><p class="m-card__subtitle">Đơn mới nhất hiển thị trước, 15 đơn mỗi trang.</p></div></header>
    @if($orders->isEmpty())
      <div class="text-center py-5"><i class="fa-solid fa-receipt fs-1 text-muted" aria-hidden="true"></i><h3 class="h5 mt-3">Không tìm thấy đơn hàng</h3><p class="text-muted mb-0">Hãy thử thay đổi điều kiện tìm kiếm hoặc bộ lọc.</p></div>
    @else
      <div class="table-responsive"><table class="m-table table align-middle">
        <thead><tr><th>Mã đơn</th><th>Khách hàng / Người nhận</th><th>Thời gian</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
        <tbody>@foreach($orders as $order)
          @php
            $statusClass = match($order->status) {'pending' => 'text-bg-warning', 'confirmed', 'processing' => 'text-bg-info', 'shipped' => 'text-bg-primary', 'delivered' => 'text-bg-success', 'cancelled' => 'text-bg-danger', default => 'text-bg-secondary'};
            $paymentClass = match($order->payment_status) {'paid' => 'text-bg-success', 'failed' => 'text-bg-danger', 'refunded' => 'text-bg-info', default => 'text-bg-secondary'};
          @endphp
          <tr>
            <td><strong>{{ $order->order_code }}</strong><div class="text-muted">#{{ $order->id }}</div></td>
            <td><strong>{{ $order->recipient_name }}</strong><div>{{ $order->recipient_phone }}</div>@if($order->user)<small class="text-success"><i class="fa-solid fa-user me-1" aria-hidden="true"></i>{{ $order->user->email }}</small>@else<small class="text-muted"><i class="fa-regular fa-user me-1" aria-hidden="true"></i>Khách vãng lai</small>@endif</td>
            <td><time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('d/m/Y H:i') }}</time></td>
            <td>{{ $order->items_count }} dòng</td>
            <td><strong>{{ $moneyFormatter->format($order->total_amount) }}</strong></td>
            <td><span class="badge {{ $paymentClass }}">{{ $paymentStatuses[$order->payment_status] ?? $order->payment_status }}</span><div class="small text-muted mt-1">{{ $paymentMethods[$order->payment_method] ?? $order->payment_method }}</div></td>
            <td><span class="badge {{ $statusClass }}">{{ $statuses[$order->status] ?? $order->status }}</span></td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa-regular fa-eye me-1" aria-hidden="true"></i>Xem</a></td>
          </tr>
        @endforeach</tbody>
      </table></div>
      <div class="mt-4">{{ $orders->links() }}</div>
    @endif
  </section>
@endsection
