@extends('admin.layout')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h3">{{ $category->exists ? 'Sửa danh mục' : 'Thêm danh mục' }}</h1><a class="btn btn-outline-secondary" href="{{ route('admin.categories.index') }}"><i class="fa-solid fa-list" aria-hidden="true"></i> Danh sách</a></div>
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="m-card">
    <form method="POST" enctype="multipart/form-data" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
      @csrf @if($category->exists) @method('PUT') @endif
      <div class="row g-3">
        @foreach(['name_vi' => 'Tên tiếng Việt', 'name_en' => 'Tên tiếng Anh'] as $field => $label)
          <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $category->$field) }}" maxlength="255" required></div>
        @endforeach
        <div class="col-md-6"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" maxlength="255"><small class="text-muted">Để trống để tạo từ tên tiếng Việt. Slug phải duy nhất.</small></div>
        <div class="col-md-6"><label class="form-label" for="parent_id">Danh mục cha</label><select class="form-select" id="parent_id" name="parent_id"><option value="">Không có (danh mục gốc)</option>@foreach($parents as $parent)<option value="{{ $parent->id }}" @selected((string)old('parent_id', $category->parent_id) === (string)$parent->id)>{{ $parent->name_vi }}</option>@endforeach</select></div>
        @include('admin.includes.catalog-image-field', ['record' => $category])
        <div class="col-md-6"><label class="form-label" for="sort_order">Thứ tự *</label><input class="form-control" type="number" id="sort_order" name="sort_order" value="{{ old('sort_order', $category->sort_order) }}" min="0" max="2147483647" required></div>
        <div class="col-12"><input type="hidden" name="status" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="status" id="status" value="1" @checked(old('status', $category->status))><label class="form-check-label" for="status">Hiển thị danh mục</label></div></div>
      </div>
      <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Lưu danh mục</button><a class="btn btn-secondary" href="{{ route('admin.categories.index') }}"><i class="fa-solid fa-times" aria-hidden="true"></i> Hủy</a></div>
    </form>
  </section>
@endsection

@section('scripts')
  @stack('catalog-image-scripts')
@endsection
