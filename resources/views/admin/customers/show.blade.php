@extends('admin.layout')

@section('content')
  @php
    $statusClass = match($customer->status) {
      'active' => 'text-bg-success',
      'blocked' => 'text-bg-danger',
      default => 'text-bg-secondary',
    };
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.customers') }}">Khách hàng</a></li><li class="breadcrumb-item active" aria-current="page">#{{ $customer->id }}</li></ol></nav>
      <h1 class="h3 mb-1">{{ $customer->display_name }}</h1>
      <p class="text-muted mb-0">Hồ sơ và trạng thái tài khoản khách hàng.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @can('customers.update')
        <form method="POST" action="{{ route('admin.customers.status.update', $customer) }}" data-confirm-title="{{ $customer->status !== 'active' ? 'Mở lại tài khoản' : 'Khóa tài khoản' }}" data-confirm-message="{{ $customer->status !== 'active' ? 'Mở lại tài khoản khách hàng này?' : 'Khóa tài khoản khách hàng này? Khách hàng sẽ không thể tiếp tục sử dụng các khu vực được bảo vệ.' }}" data-confirm-button="{{ $customer->status !== 'active' ? 'Mở tài khoản' : 'Khóa tài khoản' }}" data-confirm-tone="warning">
          @csrf @method('PATCH')
          <input type="hidden" name="status" value="{{ $customer->status !== 'active' ? 'active' : 'blocked' }}">
          @if($customer->status !== 'active')
            <button class="btn btn-success" type="submit"><i class="fa-solid fa-lock-open me-1" aria-hidden="true"></i>Mở tài khoản</button>
          @else
            <button class="btn btn-danger" type="submit"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>Khóa tài khoản</button>
          @endif
        </form>
      @endcan
      <a class="btn btn-outline-secondary" href="{{ route('admin.customers') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
      <section class="m-card h-100" aria-labelledby="customer-profile-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="customer-profile-title">Thông tin tài khoản</h2><p class="m-card__subtitle">Thông tin nhận diện và liên hệ hiện tại.</p></div><div class="d-flex flex-wrap justify-content-end gap-2"><span class="badge {{ $customerSegment['badge_class'] }}" title="{{ $customerSegment['description'] }}">{{ $customerSegment['label'] }}</span><span class="badge {{ $statusClass }}">{{ $statuses[$customer->status] ?? $customer->status }}</span></div></header>
        <dl class="row mb-0">
          <dt class="col-sm-4 py-2">Họ và tên</dt><dd class="col-sm-8 py-2">{{ $customer->display_name }}</dd>
          <dt class="col-sm-4 py-2">Tên gốc</dt><dd class="col-sm-8 py-2">{{ $customer->name }}</dd>
          <dt class="col-sm-4 py-2">Username</dt><dd class="col-sm-8 py-2">{{ '@'.$customer->username }}</dd>
          <dt class="col-sm-4 py-2">Email</dt><dd class="col-sm-8 py-2"><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a> @if($customer->email_verified_at)<span class="badge text-bg-success ms-1">Đã xác minh</span>@else<span class="badge text-bg-warning ms-1">Chưa xác minh</span>@endif</dd>
          <dt class="col-sm-4 py-2">Điện thoại</dt><dd class="col-sm-8 py-2">{{ $customer->phone ?: 'Chưa cập nhật' }}</dd>
        </dl>
      </section>
    </div>
    <div class="col-12 col-xl-5">
      <section class="m-card h-100" aria-labelledby="customer-time-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="customer-time-title">Thông tin hệ thống</h2><p class="m-card__subtitle">Các mốc thời gian của tài khoản.</p></div></header>
        <dl class="mb-0">
          <dt>Ngày đăng ký</dt><dd class="mt-1 mb-3"><time datetime="{{ $customer->created_at?->toIso8601String() }}">{{ $customer->created_at?->format('d/m/Y H:i:s') ?: 'Không xác định' }}</time></dd>
          <dt>Cập nhật gần nhất</dt><dd class="mt-1 mb-3"><time datetime="{{ $customer->updated_at?->toIso8601String() }}">{{ $customer->updated_at?->format('d/m/Y H:i:s') ?: 'Không xác định' }}</time></dd>
          <dt>ID tài khoản</dt><dd class="mt-1 mb-0">#{{ $customer->id }}</dd>
        </dl>
      </section>
    </div>
  </div>

  <section class="m-card mb-4" aria-labelledby="customer-order-summary-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="customer-order-summary-title">Thống kê mua hàng</h2><p class="m-card__subtitle">Chi tiêu và giá trị trung bình chỉ tính các đơn đã giao thành công; lần mua gần nhất không tính đơn đã hủy.</p></div></header>
    <div class="row g-3">
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Tổng số đơn</div><strong class="fs-3">{{ number_format($orderStats['total_orders']) }}</strong></div></div>
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Giao thành công</div><strong class="fs-3 text-success">{{ number_format($orderStats['delivered_orders']) }}</strong></div></div>
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Đơn bị hủy</div><strong class="fs-3 text-danger">{{ number_format($orderStats['cancelled_orders']) }}</strong></div></div>
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Tổng chi tiêu</div><strong class="fs-4 text-primary">{{ $moneyFormatter->format($orderStats['delivered_spending']) }}</strong><div class="small text-muted mt-1">Từ đơn đã giao</div></div></div>
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Giá trị đơn trung bình</div><strong class="fs-4">{{ $moneyFormatter->format($orderStats['average_delivered_order']) }}</strong><div class="small text-muted mt-1">Trên đơn đã giao</div></div></div>
      <div class="col-12 col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><div class="text-muted mb-1">Lần mua gần nhất</div>@if($orderStats['latest_purchase_at'])<strong class="fs-5"><time datetime="{{ $orderStats['latest_purchase_at']->toIso8601String() }}">{{ $orderStats['latest_purchase_at']->format('d/m/Y H:i') }}</time></strong>@else<strong class="fs-5 text-muted">Chưa có đơn hàng</strong>@endif</div></div>
    </div>
  </section>

  <section class="m-card" aria-labelledby="customer-order-history-title">
    <header class="m-card__header">
      <div><h2 class="m-card__title" id="customer-order-history-title">Lịch sử mua hàng</h2><p class="m-card__subtitle">Đơn mới nhất hiển thị trước, 10 đơn mỗi trang.</p></div>
      <span class="badge text-bg-primary">{{ number_format($orders->total()) }} đơn</span>
    </header>

    <form action="{{ route('admin.customers.show', $customer) }}" method="GET" class="row g-3 align-items-end mb-4">
      <div class="col-12 col-md-6 col-xl-4">
        <label class="form-label" for="customer-order-status">Trạng thái đơn</label>
        <select class="form-select" id="customer-order-status" name="order_status">
          <option value="">Tất cả trạng thái</option>
          @foreach($orderStatuses as $value => $label)
            <option value="{{ $value }}" @selected(($orderFilters['order_status'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-6 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Áp dụng</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.customers.show', $customer) }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Xóa lọc</a>
      </div>
    </form>

    @if($orders->isEmpty())
      <div class="text-center py-5">
        <i class="fa-solid fa-bag-shopping fs-1 text-muted" aria-hidden="true"></i>
        <h3 class="h5 mt-3">Chưa có đơn hàng phù hợp</h3>
        <p class="text-muted mb-0">Khách hàng chưa có đơn hoặc không có đơn ở trạng thái đã chọn.</p>
      </div>
    @else
      <div class="table-responsive">
        <table class="m-table table align-middle">
          <thead><tr><th>Mã đơn</th><th>Ngày đặt</th><th>Người nhận</th><th>Tổng tiền</th><th>Thanh toán</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
          <tbody>
            @foreach($orders as $order)
              @php
                $orderStatusClass = match($order->status) {
                  'pending' => 'text-bg-warning',
                  'confirmed', 'processing' => 'text-bg-info',
                  'shipped' => 'text-bg-primary',
                  'delivered' => 'text-bg-success',
                  'cancelled' => 'text-bg-danger',
                  default => 'text-bg-secondary',
                };
                $paymentStatusClass = match($order->payment_status) {
                  'paid' => 'text-bg-success',
                  'failed' => 'text-bg-danger',
                  'refunded' => 'text-bg-info',
                  default => 'text-bg-secondary',
                };
              @endphp
              <tr>
                <td><strong>{{ $order->order_code }}</strong><div class="small text-muted">#{{ $order->id }}</div></td>
                <td><time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('d/m/Y H:i') }}</time></td>
                <td>{{ $order->recipient_name }}<div class="small text-muted">{{ $order->recipient_phone }}</div></td>
                <td><strong>{{ $moneyFormatter->format($order->total_amount) }}</strong></td>
                <td><span class="badge {{ $paymentStatusClass }}">{{ \App\Models\Order::PAYMENT_STATUSES[$order->payment_status] ?? $order->payment_status }}</span></td>
                <td><span class="badge {{ $orderStatusClass }}">{{ $orderStatuses[$order->status] ?? $order->status }}</span></td>
                <td>
                  @can('orders.view')
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.orders.show', $order) }}"><i class="fa-regular fa-eye me-1" aria-hidden="true"></i>Xem đơn</a>
                  @else
                    <span class="text-muted small">Chỉ xem</span>
                  @endcan
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-4">{{ $orders->links() }}</div>
    @endif
  </section>
@endsection
