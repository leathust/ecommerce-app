@extends('admin.layout')
@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Sản phẩm</h1><span class="badge text-bg-primary"><span id="product-total">{{ number_format($products->total()) }}</span> sản phẩm</span></div><p class="text-muted mb-0">Quản lý danh sách sản phẩm của cửa hàng.</p></div>
    @can('products.create')<a class="btn btn-primary" href="{{ route('admin.products.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Thêm sản phẩm</a>@endcan
  </div>
  <div id="product-action-message" aria-live="polite">@foreach (['success' => 'success', 'error' => 'danger'] as $key => $color)@if(session($key))<div class="alert alert-{{ $color }}" role="alert">{{ session($key) }}</div>@endif @endforeach</div>
  <section class="m-card">
    <form method="GET" action="{{ route('admin.products') }}" class="d-flex gap-2 mb-4">
      <label for="product-search" class="visually-hidden">Tên hoặc SKU</label><input id="product-search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Tìm tên hoặc SKU...">
      <button class="btn btn-primary"><i class="fa-solid fa-search" aria-hidden="true"></i> Tìm</button><a class="btn btn-outline-secondary" href="{{ route('admin.products') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i> Xóa lọc</a>
    </form>
    <div id="product-list-region">@include('admin.products.partials.list')</div>
  </section>
  @can('products.delete')<div id="product-bulk-config" class="d-none" data-url="{{ route('admin.products.bulk-delete') }}" data-csrf="{{ csrf_token() }}"></div>@endcan
@endsection 
@can('products.delete')
  @push('modals')
    <div class="modal fade" id="bulk-delete-products-modal" tabindex="-1" aria-labelledby="bulk-delete-products-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
      <div class="modal-header"><h2 class="modal-title fs-5" id="bulk-delete-products-title">Xóa nhiều sản phẩm</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
      <div class="modal-body"><p>Bạn sắp xóa vĩnh viễn <strong><span id="bulk-delete-modal-count">0</span> sản phẩm</strong>.</p><p class="mb-0 text-muted">Ảnh và liên kết yêu thích sẽ bị xóa; lịch sử đơn hàng được giữ. Sản phẩm đang nằm trong giỏ hàng sẽ được bỏ qua và báo lại.</p></div>
      <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="button" class="btn btn-danger" id="bulk-delete-confirm"><span class="spinner-border spinner-border-sm me-1 d-none" aria-hidden="true"></span>Xóa sản phẩm</button></div>
    </div></div></div>
  @endpush
@endcan
@section('scripts')
  @can('products.delete')<script src="{{ asset('assets/admin/vendor/jquery-3.7.1.min.js') }}"></script><script src="{{ asset('assets/admin/js/product-bulk-delete.js') }}"></script>@endcan
@endsection
