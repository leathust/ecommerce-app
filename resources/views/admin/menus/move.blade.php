@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 mb-1">Di chuyển nhánh menu</h1><p class="text-muted mb-0">Đổi menu cha hoặc vị trí hiển thị bằng mutation riêng có kiểm tra depth và cycle.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.menus.index', ['location' => $menu->location]) }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
  </div>

  @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Không thể di chuyển menu.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('admin.menus.move', $menu) }}" class="m-card" id="menu-move-page-form">
    @csrf @method('PATCH')
    <input type="hidden" name="source_parent_id" value="{{ $menu->parent_id }}">
    <input type="hidden" name="source_location" value="{{ $menu->location }}">
    <div class="mb-4"><div class="small text-muted">Nhánh đang di chuyển</div><div class="fw-semibold fs-5">{{ $subject['breadcrumb'] }}</div><span class="badge text-bg-light border mt-1">Cấp {{ $subject['depth'] }}</span><span class="badge text-bg-light border mt-1">Chiều cao nhánh {{ $subject['subtree_height'] }}</span></div>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="location">Vị trí mới</label><select class="form-select" id="location" name="location">@foreach($locations as $key => $metadata)@if($subject['subtree_height'] === 1 || $key === $subject['location'])<option value="{{ $key }}" @selected(old('location', $subject['location']) === $key)>{{ $metadata['label'] }}</option>@endif @endforeach</select>@if($subject['subtree_height'] > 1)<small class="text-muted">Nhánh có menu con không được đổi đồng thời giữa header và footer.</small>@endif</div>
      <div class="col-md-6"><label class="form-label" for="parent_id">Menu cha mới</label><select class="form-select" id="parent_id" name="parent_id"><option value="" data-location="">Không có — mục gốc</option>@foreach($parents as $parent)<option value="{{ $parent['id'] }}" data-location="{{ $parent['location'] }}" data-depth="{{ $parent['depth'] }}" @selected((string) old('parent_id', $subject['parent_id']) === (string) $parent['id'])>{{ str_repeat('— ', max(0, $parent['depth'] - 1)) }}{{ $parent['breadcrumb'] }} ({{ $locations[$parent['location']]['label'] }})</option>@endforeach</select><small class="text-primary d-block mt-1" id="move-depth-preview" aria-live="polite"></small></div>
    </div>
    <div class="mt-4 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-right-left me-1" aria-hidden="true"></i>Di chuyển nhánh</button><a class="btn btn-outline-secondary" href="{{ route('admin.menus.index', ['location' => $menu->location]) }}">Hủy</a></div>
  </form>
@endsection

@section('scripts')
<script>
(() => {
  const location = document.getElementById('location');
  const parent = document.getElementById('parent_id');
  const preview = document.getElementById('move-depth-preview');
  const subtreeHeight = @json($subject['subtree_height']);
  const maxDepth = @json(config('menus.max_depth', 4));
  function sync() {
    Array.from(parent.options).forEach((option, index) => { if (index) option.hidden = option.dataset.location !== location.value; });
    if (parent.selectedOptions[0]?.hidden) parent.value = '';
    const parentDepth = Number(parent.selectedOptions[0]?.dataset.depth || 0);
    preview.textContent = `Nhánh sẽ bắt đầu ở cấp ${parentDepth + 1} và sâu nhất ở cấp ${parentDepth + subtreeHeight}/${maxDepth}.`;
  }
  location.addEventListener('change', sync);
  parent.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
