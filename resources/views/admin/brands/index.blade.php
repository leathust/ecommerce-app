@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Thương hiệu</h1><span class="badge text-bg-primary">{{ number_format($brands->total()) }} thương hiệu</span></div><p class="text-muted mb-0">Quản lý thương hiệu được gắn với sản phẩm.</p></div>
    @can('brands.create')<a class="btn btn-primary" href="{{ route('admin.brands.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Thêm thương hiệu</a>@endcan
  </div>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  <section class="m-card">
    <form method="GET" action="{{ route('admin.brands.index') }}" class="d-flex gap-2 mb-4">
      <label class="visually-hidden" for="brand-search">Tìm thương hiệu</label>
      <input class="form-control" id="brand-search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tên hoặc slug...">
      <button class="btn btn-primary"><i class="fa-solid fa-search" aria-hidden="true"></i> Tìm</button><a class="btn btn-outline-secondary" href="{{ route('admin.brands.index') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i> Xóa lọc</a>
    </form>
    <div class="table-responsive"><table class="m-table table align-middle">
      <thead><tr><th>ID</th><th>Thương hiệu</th><th>Slug</th><th>Sản phẩm</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        @forelse($brands as $brand)
          <tr>
            <td>{{ $brand->id }}@if($brand->image_src)<div><img src="{{ $brand->image_src }}" alt="{{ $brand->name_vi }}" width="64" height="64" class="rounded mt-2" style="object-fit:contain"></div>@endif</td><td><strong>{{ $brand->name_vi }}</strong><div class="text-muted">{{ $brand->name_en }}</div></td>
            <td>{{ $brand->slug }}</td><td>{{ $brand->products_count }}</td>
            <td><span class="badge {{ $brand->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $brand->status ? 'Hiển thị' : 'Ẩn' }}</span></td>
            <td><div class="d-flex gap-2">
              @can('brands.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.brands.edit', $brand) }}">Sửa</a>@endcan
              @can('brands.delete')
                <form method="POST" action="{{ route('admin.brands.destroy', $brand) }}" data-confirm-title="Xóa thương hiệu" data-confirm-message="Xóa vĩnh viễn thương hiệu này? Sản phẩm vẫn được giữ nhưng sẽ không còn gắn thương hiệu." data-confirm-button="Xóa thương hiệu">
                  @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button>
                </form>
              @endcan
            </div></td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center py-5 text-muted">Chưa có thương hiệu phù hợp.</td></tr>
        @endforelse
      </tbody>
    </table></div>
    <div class="mt-4">{{ $brands->links() }}</div>
  </section>
@endsection
