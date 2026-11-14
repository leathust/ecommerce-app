@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Menu website</h1><span class="badge text-bg-primary">{{ number_format($totalMenus) }} mục</span></div>
      <p class="text-muted mb-0">Quản lý cây điều hướng tối đa {{ config('menus.max_depth', 4) }} cấp cho website khách hàng.</p>
    </div>
    @can('menus.create')<a class="btn btn-primary" href="{{ route('admin.menus.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Thêm menu</a>@endcan
  </div>

  @foreach(['success' => 'success', 'error' => 'danger'] as $key => $color)
    @if(session($key))<div class="alert alert-{{ $color }}" role="alert">{{ session($key) }}</div>@endif
  @endforeach
  <div id="menu-ajax-feedback" class="d-none alert" role="status" aria-live="polite"></div>

  <section class="m-card mb-4">
    <form method="GET" action="{{ route('admin.menus.index') }}" class="row g-3 align-items-end">
      <div class="col-lg-5"><label class="form-label" for="menu-search">Tìm kiếm</label><input class="form-control" id="menu-search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tên, route hoặc URL..."></div>
      <div class="col-md-4 col-lg-3"><label class="form-label" for="menu-location">Vị trí</label><select class="form-select" id="menu-location" name="location">@foreach($locations as $key => $metadata)<option value="{{ $key }}" @selected($filters['location'] === $key)>{{ $metadata['label'] }}</option>@endforeach</select></div>
      <div class="col-md-4 col-lg-2"><label class="form-label" for="menu-status">Trạng thái</label><select class="form-select" id="menu-status" name="status"><option value="">Tất cả</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Hiển thị</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đang ẩn</option></select></div>
      <div class="col-md-4 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1" type="submit"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Lọc</button><a class="btn btn-outline-secondary" href="{{ route('admin.menus.index') }}" title="Đặt lại"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span class="visually-hidden">Đặt lại</span></a></div>
    </form>
  </section>

  <section class="m-card">
    <div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><h2 class="h5 mb-1">{{ $locations[$filters['location']]['label'] }}</h2><p class="text-muted mb-0">{{ $locations[$filters['location']]['description'] }}</p></div><span class="small text-muted">Phân trang theo mục gốc để không tách cây cha–con.</span></div>
    <div class="table-responsive"><table class="m-table table align-middle">
      <thead><tr><th>ID</th><th>Tên menu</th><th>Liên kết</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        @forelse($menuTrees as $root)
          @include('admin.menus.partials.row', [
            'item' => $root,
            'level' => 0,
            'scopeIdsByParent' => $scopeIdsByParent,
            'ancestorActive' => true,
            'ancestorLabels' => [],
            'moveCatalogById' => $moveCatalogById,
          ])
        @empty
          <tr><td colspan="6" class="text-center py-5 text-muted"><i class="fa-solid fa-diagram-project d-block fs-2 mb-2" aria-hidden="true"></i>Chưa có menu phù hợp với bộ lọc.</td></tr>
        @endforelse
      </tbody>
    </table></div>
    <div class="mt-4">{{ $menuTrees->links() }}</div>
  </section>

  @can('menus.update')
    <script type="application/json" id="menu-move-catalog" data-max-depth="{{ config('menus.max_depth', 4) }}">@json($moveCatalog)</script>
  @endcan
@endsection

@push('modals')
  @can('menus.update')
    <div class="modal fade" id="menu-move-modal" tabindex="-1" aria-labelledby="menu-move-title" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <form method="POST" action="" data-menu-move-form>
            @csrf @method('PATCH')
            <input type="hidden" name="source_parent_id" value="">
            <input type="hidden" name="source_location" value="">
            <div class="modal-header"><h2 class="modal-title fs-5" id="menu-move-title">Di chuyển nhánh menu</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button></div>
            <div class="modal-body">
              <p class="mb-3">Đang di chuyển: <strong data-menu-move-name></strong></p>
              <div class="mb-3"><label class="form-label" for="move-menu-location">Vị trí</label><select class="form-select" id="move-menu-location" name="location">@foreach($locations as $key => $metadata)<option value="{{ $key }}">{{ $metadata['label'] }}</option>@endforeach</select></div>
              <div class="mb-2"><label class="form-label" for="move-menu-parent">Menu cha mới</label><select class="form-select" id="move-menu-parent" name="parent_id"><option value="">Không có — mục gốc</option></select></div>
              <p class="small text-muted mb-0" data-menu-move-depth aria-live="polite"></p>
              <div class="alert alert-warning py-2 mt-3 mb-0 d-none" data-menu-move-warning></div>
            </div>
            <div class="modal-footer"><a class="btn btn-outline-secondary me-auto" href="#" data-menu-move-page>Mở trang di chuyển</a><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-primary"><i class="fa-solid fa-right-left me-1" aria-hidden="true"></i>Di chuyển</button></div>
          </form>
        </div>
      </div>
    </div>
  @endcan
@endpush

@section('scripts')
  <script src="{{ asset('assets/admin/js/menu-manager.js') }}"></script>
@endsection
