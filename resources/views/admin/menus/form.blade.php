@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><h1 class="h3 mb-1">{{ $menu->exists ? 'Cập nhật menu' : 'Thêm menu' }}</h1><p class="text-muted mb-0">Thiết lập nhãn song ngữ, vị trí trong cây và đích điều hướng an toàn.</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.menus.index', ['location' => old('location', $menu->location)]) }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
  </div>

  @if($errors->any())<div class="alert alert-danger" role="alert"><strong>Không thể lưu menu.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @if($positionLocked)<div class="alert alert-info" role="alert"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Vị trí và menu cha được khóa tại form thông tin. Hãy dùng nút <strong>Di chuyển</strong> trong danh sách để thay đổi cấu trúc cây với kiểm tra depth và audit riêng.</div>@endif

  <form method="POST" action="{{ $menu->exists ? route('admin.menus.update', $menu) : route('admin.menus.store') }}" id="menu-form">
    @csrf @if($menu->exists) @method('PUT') @endif
    <div class="row g-4">
      <div class="col-xl-8">
        <section class="m-card h-100">
          <h2 class="h5 mb-3">Thông tin menu</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="name_vi">Tên tiếng Việt *</label><input class="form-control" id="name_vi" name="name_vi" maxlength="255" required value="{{ old('name_vi', $menu->name_vi) }}"></div>
            <div class="col-md-6"><label class="form-label" for="name_en">Tên tiếng Anh *</label><input class="form-control" id="name_en" name="name_en" maxlength="255" required value="{{ old('name_en', $menu->name_en) }}"></div>
            <div class="col-md-6"><label class="form-label" for="location">Vị trí *</label><select class="form-select" id="location" name="location" @disabled($positionLocked)>@foreach($locations as $key => $metadata)<option value="{{ $key }}" @selected(old('location', $menu->location) === $key)>{{ $metadata['label'] }}</option>@endforeach</select>@if($positionLocked)<input type="hidden" name="location" value="{{ old('location', $menu->location) }}">@endif</div>
            <div class="col-md-6">
              <label class="form-label" for="parent_id">Menu cha</label>
              <select class="form-select" id="parent_id" name="parent_id" @disabled($positionLocked)>
                <option value="" data-depth="0">Không có — mục gốc</option>
                @foreach($parents as $parent)
                  <option value="{{ $parent->id }}" data-location="{{ $parent->location }}" data-depth="{{ $parent->tree_depth }}" @selected((string) old('parent_id', $menu->parent_id) === (string) $parent->id)>{{ str_repeat('— ', max(0, $parent->tree_depth - 1)) }}{{ $parent->breadcrumb }} ({{ $locations[$parent->location]['label'] }})</option>
                @endforeach
              </select>
              @if($positionLocked)<input type="hidden" name="parent_id" value="{{ old('parent_id', $menu->parent_id) }}">@endif
              <small class="text-muted d-block">Chỉ hiển thị parent cùng vị trí có thể giữ toàn bộ nhánh trong giới hạn {{ $maxDepth }} cấp.</small>
              <small class="text-primary d-block mt-1" id="menu-parent-depth" aria-live="polite"></small>
            </div>
            <div class="col-md-6"><label class="form-label" for="sort_order">Thứ tự *</label><input class="form-control" type="number" id="sort_order" name="sort_order" min="0" max="2147483647" required value="{{ old('sort_order', $menu->sort_order) }}"></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input type="hidden" name="status" value="0"><input class="form-check-input" type="checkbox" id="status" name="status" value="1" @checked(old('status', $menu->status))><label class="form-check-label" for="status">Hiển thị menu</label></div></div>
          </div>
        </section>
      </div>
      <div class="col-xl-4">
        <section class="m-card h-100"><h2 class="h5 mb-3">Xem trước</h2><div class="border rounded-3 p-3 bg-light"><div class="small text-muted mb-1" id="menu-preview-location"></div><a href="#" id="menu-preview-link" class="fw-semibold text-decoration-none" onclick="return false"></a><div class="small text-muted text-break mt-2" id="menu-preview-url"></div></div><p class="small text-muted mt-3 mb-0">Preview chỉ minh họa nhãn và liên kết; trạng thái thực tế còn phụ thuộc cây cha và storefront.</p></section>
      </div>
      <div class="col-12">
        <section class="m-card">
          <h2 class="h5 mb-3">Đích điều hướng</h2>
          <div class="row g-3">
            <div class="col-md-4"><label class="form-label" for="link_type">Kiểu liên kết *</label><select class="form-select" id="link_type" name="link_type">@foreach($linkTypes as $key => $label)<option value="{{ $key }}" @selected(old('link_type', $menu->link_type) === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-4" data-link-field="route"><label class="form-label" for="route_name">Route nội bộ *</label><select class="form-select" id="route_name" name="route_name"><option value="">Chọn route</option>@foreach($routes as $name => $metadata)<option value="{{ $name }}" @selected(old('route_name', $menu->route_name) === $name)>{{ $metadata['label_vi'] }} — {{ $name }}</option>@endforeach</select></div>
            <div class="col-md-4" data-link-field="url"><label class="form-label" for="url">URL *</label><input class="form-control" id="url" name="url" maxlength="2048" value="{{ old('url', $menu->url) }}" placeholder="/contact hoặc https://example.com"><small class="text-muted">Không chấp nhận javascript:, credential URL hoặc đường dẫn /admin.</small></div>
            <div class="col-md-4"><label class="form-label" for="target">Cách mở *</label><select class="form-select" id="target" name="target">@foreach($targets as $key => $label)<option value="{{ $key }}" @selected(old('target', $menu->target) === $key)>{{ $label }}</option>@endforeach</select></div>
          </div>
        </section>
      </div>
    </div>
    <div class="mt-4 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>{{ $menu->exists ? 'Lưu thay đổi' : 'Tạo menu' }}</button><a class="btn btn-outline-secondary" href="{{ route('admin.menus.index', ['location' => old('location', $menu->location)]) }}">Hủy</a></div>
  </form>
@endsection

@section('scripts')
<script>
(() => {
  'use strict';
  const routeUrls = @json($routeUrls);
  const form = document.getElementById('menu-form');
  if (!form) return;
  const type = document.getElementById('link_type');
  const location = document.getElementById('location');
  const parent = document.getElementById('parent_id');
  const name = document.getElementById('name_vi');
  const routeName = document.getElementById('route_name');
  const url = document.getElementById('url');
  const target = document.getElementById('target');
  const previewLink = document.getElementById('menu-preview-link');
  const previewUrl = document.getElementById('menu-preview-url');
  const previewLocation = document.getElementById('menu-preview-location');
  const parentDepth = document.getElementById('menu-parent-depth');
  const maxDepth = @json($maxDepth);
  const subjectHeight = @json($subjectHeight);

  function sync() {
    document.querySelectorAll('[data-link-field]').forEach(field => field.classList.toggle('d-none', field.dataset.linkField !== type.value));
    if (parent && location) Array.from(parent.options).forEach((option, index) => { if (index) option.hidden = option.dataset.location !== location.value; });
    if (parent?.selectedOptions[0]?.hidden) parent.value = '';
    const selectedParentDepth = Number(parent?.selectedOptions[0]?.dataset.depth || 0);
    const itemDepth = selectedParentDepth + 1;
    const deepestDepth = selectedParentDepth + subjectHeight;
    if (parentDepth) parentDepth.textContent = `Vị trí dự kiến: cấp ${itemDepth}; nhánh sâu nhất: cấp ${deepestDepth}/${maxDepth}.`;
    const href = type.value === 'route' ? (routeUrls[routeName.value] || '') : (type.value === 'url' ? url.value.trim() : '');
    previewLink.textContent = name.value.trim() || 'Tên menu';
    previewLink.target = target.value;
    previewUrl.textContent = type.value === 'none' ? 'Nhóm chứa — không có liên kết' : (href || 'Chưa chọn liên kết');
    previewLocation.textContent = location?.selectedOptions[0]?.textContent || '';
  }
  [type, location, parent, name, routeName, url, target].filter(Boolean).forEach(element => element.addEventListener('input', sync));
  sync();
})();
</script>
@endsection
