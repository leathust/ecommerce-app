@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Danh mục</h1><span class="badge text-bg-primary">{{ number_format($categories->total()) }} danh mục</span></div><p class="text-muted mb-0">Tổ chức và quản lý danh mục sản phẩm.</p></div>
    @can('categories.create')<a class="btn btn-primary" href="{{ route('admin.categories.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Thêm danh mục</a>@endcan
  </div>
  @foreach(['success' => 'success', 'error' => 'danger'] as $key => $color)
    @if(session($key))<div class="alert alert-{{ $color }}" role="alert">{{ session($key) }}</div>@endif
  @endforeach
  <section class="m-card">
    <form method="GET" action="{{ route('admin.categories.index') }}" class="d-flex gap-2 mb-4">
      <label class="visually-hidden" for="category-search">Tìm danh mục</label>
      <input class="form-control" id="category-search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tên hoặc slug...">
      <button class="btn btn-primary"><i class="fa-solid fa-search" aria-hidden="true"></i> Tìm</button><a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Xóa lọc</a>
    </form>
    <div class="table-responsive"><table class="m-table table align-middle">
      <thead><tr><th>ID</th><th>Danh mục</th><th>Danh mục cha</th><th>Sản phẩm / Con</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        @forelse($categories as $category)
          <tr>
            <td>{{ $category->id }}@if($category->image_src)<div><img src="{{ $category->image_src }}" alt="{{ $category->name_vi }}" width="64" height="64" class="rounded mt-2" style="object-fit:contain"></div>@endif</td><td><strong>{{ $category->name_vi }}</strong><div>{{ $category->name_en }}</div><small class="text-muted">{{ $category->slug }}</small></td>
            <td>{{ $category->parent?->name_vi ?? 'Danh mục gốc' }}</td>
            <td>{{ $category->products_count }} / {{ $category->children_count }}</td><td>{{ $category->sort_order }}</td>
            <td><span class="badge {{ $category->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->status ? 'Hiển thị' : 'Ẩn' }}</span></td>
            <td><div class="d-flex gap-2">
              @can('categories.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.categories.edit', $category) }}">Sửa</a>@endcan
              @can('categories.delete')
                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" data-confirm-title="Xóa danh mục" data-confirm-message="Xóa vĩnh viễn danh mục này? Danh mục con sẽ trở thành danh mục gốc." data-confirm-button="Xóa danh mục">
                  @csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button>
                </form>
              @endcan
            </div></td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center py-5 text-muted">Chưa có danh mục phù hợp.</td></tr>
        @endforelse
      </tbody>
    </table></div>
    <div class="mt-4">{{ $categories->links() }}</div>
  </section>
@endsection
