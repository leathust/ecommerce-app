@extends('admin.layout')

@section('content')
  @php
    $statusClass = match($order->status) {'pending' => 'text-bg-warning', 'confirmed', 'processing' => 'text-bg-info', 'shipped' => 'text-bg-primary', 'delivered' => 'text-bg-success', 'cancelled' => 'text-bg-danger', default => 'text-bg-secondary'};
    $paymentClass = match($order->payment_status) {'paid' => 'text-bg-success', 'failed' => 'text-bg-danger', 'refunded' => 'text-bg-info', default => 'text-bg-secondary'};
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.orders') }}">Đơn hàng</a></li><li class="breadcrumb-item active" aria-current="page">{{ $order->order_code }}</li></ol></nav>
      <div class="d-flex flex-wrap align-items-center gap-2"><h1 class="h3 mb-0">{{ $order->order_code }}</h1><span class="badge {{ $statusClass }}">{{ $statuses[$order->status] ?? $order->status }}</span></div>
      <p class="text-muted mt-2 mb-0">Tạo lúc <time datetime="{{ $order->created_at?->toIso8601String() }}">{{ $order->created_at?->format('d/m/Y H:i:s') }}</time></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @if($availableTransitions !== [])
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#order-status-modal"><i class="fa-solid fa-arrows-rotate me-1" aria-hidden="true"></i>Cập nhật trạng thái</button>
      @endif
      <a class="btn btn-outline-primary" href="{{ route('admin.orders.print', $order) }}" target="_blank" rel="noopener"><i class="fa-solid fa-print me-1" aria-hidden="true"></i>In hóa đơn</a>
      <a class="btn btn-outline-secondary" href="{{ route('admin.orders') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
    </div>
  </div>

  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

  @if($availableTransitions === [] && $order->allowedTransitions() === [])
    <div class="alert alert-secondary">Đơn hàng đang ở trạng thái kết thúc, không còn bước chuyển tiếp.</div>
  @endif

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-7"><section class="m-card h-100" aria-labelledby="shipping-title">
      <header class="m-card__header"><div><h2 class="m-card__title" id="shipping-title">Thông tin giao hàng</h2><p class="m-card__subtitle">Snapshot người nhận tại thời điểm đặt hàng.</p></div></header>
      <dl class="row mb-0">
        <dt class="col-sm-4 py-2">Người nhận</dt><dd class="col-sm-8 py-2">{{ $order->recipient_name }}</dd>
        <dt class="col-sm-4 py-2">Điện thoại</dt><dd class="col-sm-8 py-2"><a href="tel:{{ $order->recipient_phone }}">{{ $order->recipient_phone }}</a></dd>
        <dt class="col-sm-4 py-2">Địa chỉ</dt><dd class="col-sm-8 py-2" style="white-space:pre-wrap">{{ $order->shipping_address }}</dd>
        <dt class="col-sm-4 py-2">Ghi chú</dt><dd class="col-sm-8 py-2" style="white-space:pre-wrap">{{ $order->note ?: 'Không có' }}</dd>
      </dl>
    </section></div>
    <div class="col-12 col-xl-5"><section class="m-card h-100" aria-labelledby="account-title">
      <header class="m-card__header"><div><h2 class="m-card__title" id="account-title">Tài khoản đặt hàng</h2><p class="m-card__subtitle">Liên kết tài khoản không làm thay đổi snapshot giao hàng.</p></div></header>
      @if($customer)
        <p class="mb-1"><strong>{{ $customer->display_name }}</strong></p><p class="mb-3"><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></p>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.customers.show', $customer) }}"><i class="fa-regular fa-user me-1" aria-hidden="true"></i>Xem hồ sơ khách hàng</a>
      @elseif($order->user)
        <p class="mb-0 text-muted">Tài khoản liên kết không thuộc nhóm khách hàng và không thể mở từ module này.</p>
      @else
        <div class="text-muted"><i class="fa-regular fa-user me-1" aria-hidden="true"></i>Đơn hàng của khách vãng lai</div>
      @endif
    </section></div>
  </div>

  <section class="m-card mb-4" aria-labelledby="order-items-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="order-items-title">Sản phẩm trong đơn</h2><p class="m-card__subtitle">Tên, SKU và giá được lưu dạng snapshot, không phụ thuộc dữ liệu sản phẩm hiện tại.</p></div></header>
    <div class="table-responsive"><table class="m-table table align-middle">
      <thead><tr><th>#</th><th>Sản phẩm</th><th>SKU</th><th class="text-end">Đơn giá</th><th class="text-end">Số lượng</th><th class="text-end">Thành tiền</th></tr></thead>
      <tbody>@forelse($order->items as $item)<tr><td>{{ $loop->iteration }}</td><td><strong>{{ $item->product_name }}</strong>@if(!$item->product_id)<div><small class="text-muted">Sản phẩm gốc không còn tồn tại</small></div>@endif</td><td>{{ $item->sku ?: '—' }}</td><td class="text-end">{{ $moneyFormatter->format($item->price) }}</td><td class="text-end">{{ $item->quantity }}</td><td class="text-end"><strong>{{ $moneyFormatter->format($item->total_price) }}</strong></td></tr>@empty<tr><td colspan="6" class="text-center py-4 text-muted">Đơn hàng chưa có dòng sản phẩm.</td></tr>@endforelse</tbody>
      <tfoot>
        <tr><th colspan="5" class="text-end">Tạm tính</th><td class="text-end">{{ $moneyFormatter->format($order->subtotal) }}</td></tr>
        <tr><th colspan="5" class="text-end">Phí giao hàng</th><td class="text-end">{{ $moneyFormatter->format($order->shipping_fee) }}</td></tr>
        <tr><th colspan="5" class="text-end fs-5">Tổng cộng</th><td class="text-end fs-5"><strong>{{ $moneyFormatter->format($order->total_amount) }}</strong></td></tr>
      </tfoot>
    </table></div>
  </section>

  <div class="row g-4">
    <div class="col-12 col-xl-5"><section class="m-card h-100" aria-labelledby="payment-title">
      <header class="m-card__header"><div><h2 class="m-card__title" id="payment-title">Thanh toán và tiến trình</h2></div><span class="badge {{ $paymentClass }}">{{ $paymentStatuses[$order->payment_status] ?? $order->payment_status }}</span></header>
      <dl class="row mb-0">
        <dt class="col-sm-5 py-2">Phương thức</dt><dd class="col-sm-7 py-2">{{ $paymentMethods[$order->payment_method] ?? $order->payment_method }}</dd>
        @foreach(['confirmed_at' => 'Xác nhận', 'processing_at' => 'Xử lý', 'shipped_at' => 'Bàn giao vận chuyển', 'delivered_at' => 'Giao thành công', 'cancelled_at' => 'Hủy đơn'] as $field => $label)
          <dt class="col-sm-5 py-2">{{ $label }}</dt><dd class="col-sm-7 py-2">{{ $order->{$field}?->format('d/m/Y H:i:s') ?? '—' }}</dd>
        @endforeach
        <dt class="col-sm-5 py-2">Trừ tồn kho</dt><dd class="col-sm-7 py-2">{{ $order->stock_deducted_at?->format('d/m/Y H:i:s') ?? 'Chưa trừ' }}</dd>
        <dt class="col-sm-5 py-2">Hoàn tồn kho</dt><dd class="col-sm-7 py-2">{{ $order->stock_restored_at?->format('d/m/Y H:i:s') ?? 'Chưa hoàn' }}</dd>
        @if($order->cancel_reason)<dt class="col-sm-5 py-2">Lý do hủy</dt><dd class="col-sm-7 py-2 text-danger" style="white-space:pre-wrap">{{ $order->cancel_reason }}</dd>@endif
      </dl>
    </section></div>
    <div class="col-12 col-xl-7"><section class="m-card h-100" aria-labelledby="history-title">
      <header class="m-card__header"><div><h2 class="m-card__title" id="history-title">Lịch sử trạng thái</h2><p class="m-card__subtitle">Timeline mọi lần chuyển trạng thái của đơn.</p></div></header>
      @forelse($order->statusHistories as $history)
        <div class="d-flex gap-3 pb-3 mb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
          <div><i class="fa-solid fa-circle-check text-primary" aria-hidden="true"></i></div>
          <div class="flex-grow-1"><div><strong>{{ $history->from_status ? ($statuses[$history->from_status] ?? $history->from_status).' → ' : '' }}{{ $statuses[$history->to_status] ?? $history->to_status }}</strong></div><div class="small text-muted">{{ $history->created_at?->format('d/m/Y H:i:s') }} · {{ $history->changedBy?->display_name ?? 'Hệ thống' }}</div>@if($history->note)<div class="mt-1" style="white-space:pre-wrap">{{ $history->note }}</div>@endif</div>
        </div>
      @empty
        <p class="text-muted mb-0">Chưa có lịch sử trạng thái.</p>
      @endforelse
    </section></div>
  </div>
@endsection

@if($availableTransitions !== [])
  @push('modals')
    <div class="modal fade" id="order-status-modal" tabindex="-1" aria-labelledby="order-status-modal-title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
          <form method="POST" action="{{ route('admin.orders.status.update', $order) }}" id="order-status-form">
            @csrf @method('PATCH')
            <div class="modal-header">
              <div>
                <h2 class="modal-title fs-5" id="order-status-modal-title">Cập nhật trạng thái đơn hàng</h2>
                <p class="text-muted small mb-0 mt-1">Đơn {{ $order->order_code }} · Hiện tại: <strong>{{ $statuses[$order->status] ?? $order->status }}</strong></p>
              </div>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label fw-semibold" for="next-order-status">Trạng thái tiếp theo</label>
                <select class="form-select" id="next-order-status" name="status" required>
                  <option value="">Chọn trạng thái</option>
                  @foreach($availableTransitions as $status)<option value="{{ $status }}" @selected(old('status') === $status)>{{ $statuses[$status] }}</option>@endforeach
                </select>
                <div class="form-text">Chỉ hiển thị các bước chuyển tiếp hợp lệ theo quyền của bạn.</div>
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold" for="status-note">Ghi chú xử lý <span class="text-muted fw-normal">(không bắt buộc)</span></label>
                <textarea class="form-control" id="status-note" name="note" rows="3" maxlength="1000" placeholder="Ví dụ: Đã xác nhận thông tin với khách hàng...">{{ old('note') }}</textarea>
              </div>
              <div class="d-none" id="cancel-reason-group">
                <div class="order-status-cancel-box">
                  <span class="order-status-cancel-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
                  <div class="flex-grow-1">
                    <label class="form-label fw-semibold" for="cancel-reason">Lý do hủy đơn <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="cancel-reason" name="cancel_reason" rows="2" maxlength="1000" placeholder="Nêu rõ lý do hủy để lưu vào lịch sử đơn hàng...">{{ old('cancel_reason') }}</textarea>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Đóng</button>
              <button class="btn btn-primary" type="submit" id="status-submit"><i class="fa-solid fa-floppy-disk me-2" aria-hidden="true"></i>Cập nhật trạng thái</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endpush
@endif

@section('styles')
  <style>
    #order-status-modal .modal-header { align-items: flex-start; }
    #order-status-modal .form-control { resize: vertical; }
    .order-status-cancel-box { display: flex; align-items: flex-start; gap: 12px; padding: 15px; border: 1px solid rgba(220, 53, 69, .25); border-radius: 12px; background: rgba(220, 53, 69, .045); }
    .order-status-cancel-icon { width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 36px; border-radius: 10px; background: rgba(220, 53, 69, .12); color: #dc3545; }
  </style>
@endsection

@section('scripts')
  <script>
    const statusSelect = document.getElementById('next-order-status');
    const cancelGroup = document.getElementById('cancel-reason-group');
    const cancelReason = document.getElementById('cancel-reason');
    const statusForm = document.getElementById('order-status-form');
    const statusSubmit = document.getElementById('status-submit');
    function toggleCancelReason() {
      if (!statusSelect) return;
      const cancelling = statusSelect.value === 'cancelled';
      cancelGroup?.classList.toggle('d-none', !cancelling);
      if (cancelReason) cancelReason.required = cancelling;
    }
    statusSelect?.addEventListener('change', toggleCancelReason);
    statusForm?.addEventListener('submit', function () { statusSubmit.disabled = true; statusSubmit.textContent = 'Đang lưu...'; });
    toggleCancelReason();
    @if($errors->any() && $availableTransitions !== [])
      bootstrap.Modal.getOrCreateInstance(document.getElementById('order-status-modal')).show();
    @endif
  </script>
@endsection
