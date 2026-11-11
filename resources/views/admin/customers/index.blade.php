@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Khách hàng</h1><span class="badge text-bg-primary">{{ number_format($customers->total()) }} khách hàng</span></div>
      <p class="text-muted mb-0">Tra cứu và theo dõi tài khoản khách hàng trong hệ thống.</p>
    </div>
  </div>

  <section class="m-card mb-4" aria-labelledby="customer-filter-title">
    <header class="m-card__header">
      <div><h2 class="m-card__title" id="customer-filter-title">Tìm kiếm và lọc</h2><p class="m-card__subtitle">Tìm theo tên, username, email hoặc số điện thoại.</p></div>
    </header>
    <form action="{{ route('admin.customers') }}" method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-lg-7">
        <label class="form-label" for="customer-search">Tìm kiếm</label>
        <input id="customer-search" class="form-control" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Tên, username, email hoặc số điện thoại...">
      </div>
      <div class="col-12 col-md-7 col-lg-3">
        <label class="form-label" for="customer-status">Trạng thái</label>
        <select id="customer-status" class="form-select" name="status">
          <option value="">Tất cả trạng thái</option>
          @foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach
        </select>
      </div>
      <div class="col-12 col-md-5 col-lg-2 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Lọc</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.customers') }}" aria-label="Xóa bộ lọc"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Đặt lại</a>
      </div>
    </form>
  </section>

  <section class="m-card" aria-labelledby="customer-list-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="customer-list-title">Danh sách tài khoản</h2><p class="m-card__subtitle">Mới nhất trước, 15 khách hàng mỗi trang.</p></div></header>
    @if($customers->isEmpty())
      <div class="text-center py-5"><i class="fa-solid fa-users-slash fs-1 text-muted" aria-hidden="true"></i><h3 class="h5 mt-3">Không tìm thấy khách hàng</h3><p class="text-muted mb-0">Hãy thử thay đổi từ khóa hoặc trạng thái.</p></div>
    @else
      <div class="table-responsive"><table class="m-table table align-middle">
        <thead><tr><th scope="col">ID</th><th scope="col">Khách hàng</th><th scope="col">Liên hệ</th><th scope="col">Phân nhóm</th><th scope="col">Trạng thái</th><th scope="col">Ngày đăng ký</th><th scope="col">Thao tác</th></tr></thead>
        <tbody>@foreach($customers as $customer)
          @php
            $statusClass = match($customer->status) {'active' => 'text-bg-success', 'blocked' => 'text-bg-danger', default => 'text-bg-secondary'};
            $segment = $customer->customerSegment();
          @endphp
          <tr>
            <td>#{{ $customer->id }}</td>
            <td><strong>{{ $customer->display_name }}</strong><div class="text-muted">{{ '@'.$customer->username }}</div></td>
            <td><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a>@if($customer->phone)<div class="text-muted">{{ $customer->phone }}</div>@endif</td>
            <td><span class="badge {{ $segment['badge_class'] }}" title="{{ $segment['description'] }}">{{ $segment['label'] }}</span><div class="small text-muted mt-1">{{ $customer->delivered_orders_count }} đơn đã giao</div></td>
            <td><span class="badge {{ $statusClass }}">{{ $statuses[$customer->status] ?? $customer->status }}</span></td>
            <td><time datetime="{{ $customer->created_at?->toIso8601String() }}">{{ $customer->created_at?->format('d/m/Y H:i') }}</time></td>
            <td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.customers.show', $customer) }}"><i class="fa-regular fa-eye me-1" aria-hidden="true"></i>Xem</a></td>
          </tr>
        @endforeach</tbody>
      </table></div>
      <div class="mt-4">{{ $customers->links() }}</div>
    @endif
  </section>
@endsection
