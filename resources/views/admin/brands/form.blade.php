@extends('admin.layout')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4"><h1 class="h3">{{ $brand->exists ? 'Sửa thương hiệu' : 'Thêm thương hiệu' }}</h1><a class="btn btn-outline-secondary" href="{{ route('admin.brands.index') }}"><i class="fa-solid fa-list" aria-hidden="true"></i> Danh sách</a></div>
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <section class="m-card">
    <form method="POST" enctype="multipart/form-data" action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}">
      @csrf @if($brand->exists) @method('PUT') @endif
      <div class="row g-3">
        @foreach(['name_vi' => 'Tên tiếng Việt', 'name_en' => 'Tên tiếng Anh'] as $field => $label)
          <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $brand->$field) }}" maxlength="255" required></div>
        @endforeach
        <div class="col-md-6"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $brand->slug) }}" maxlength="255"><small class="text-muted">Để trống để tạo từ tên tiếng Việt. Slug duy nhất, chỉ chữ thường, số và dấu gạch nối.</small></div>
        @include('admin.includes.catalog-image-field', ['record' => $brand])
        <div class="col-12"><input type="hidden" name="status" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="status" id="status" value="1" @checked(old('status', $brand->status))><label class="form-check-label" for="status">Hiển thị thương hiệu</label></div></div>
      </div>
      <div class="mt-4 d-flex gap-2"><button class="btn btn-primary"><i class="fa-solid fa-save" aria-hidden="true"></i> Lưu thương hiệu</button><a class="btn btn-secondary" href="{{ route('admin.brands.index') }}"><i class="fa-solid fa-times" aria-hidden="true"></i> Hủy</a></div>
    </form>
  </section>
@endsection

@section('scripts')
  @stack('catalog-image-scripts')
@endsection
