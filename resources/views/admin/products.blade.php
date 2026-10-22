@extends('admin.layout')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 class="h3 mb-1">Sản phẩm</h1>
      <p class="text-muted mb-0">Quản lý danh sách sản phẩm của cửa hàng.</p>
    </div>
    @can('products.create')
      <button class="btn btn-primary" type="button">Thêm sản phẩm</button>
    @endcan
  </div>
  <div class="m-card"><p class="mb-0">Màn hình quản lý sản phẩm sẽ được triển khai ở bước CRUD.</p></div>
@endsection
