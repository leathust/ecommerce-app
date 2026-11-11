@extends('admin.layout')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">{{ $product->exists ? 'Sửa sản phẩm' : 'Thêm sản phẩm' }}</h1>
    <a href="{{ route('admin.products') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-list" aria-hidden="true"></i> Danh sách</a>
  </div>
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @if($categories->isEmpty())<div class="alert alert-warning">Chưa có danh mục. Cần tạo dữ liệu trong bảng categories trước khi lưu sản phẩm.</div>@endif
  <section class="m-card">
    <form method="POST" enctype="multipart/form-data" action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}">
      @csrf
      @if($product->exists) @method('PUT') @endif
      <div class="row g-3">
        @foreach(['name_vi' => 'Tên tiếng Việt', 'name_en' => 'Tên tiếng Anh', 'sku' => 'SKU'] as $field => $label)
          <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }} *</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $product->$field) }}" maxlength="{{ $field === 'sku' ? 100 : 255 }}" required></div>
        @endforeach
        <div class="col-md-6"><label class="form-label" for="slug">Slug</label><input class="form-control" id="slug" name="slug" value="{{ old('slug', $product->slug) }}" maxlength="255"><small class="text-muted">Để trống để tạo từ tên tiếng Việt; phải duy nhất, chỉ chữ thường, số và dấu gạch nối.</small></div>
        <div class="col-md-6"><label class="form-label" for="category_id">Danh mục *</label><select class="form-select" name="category_id" id="category_id" required><option value="">Chọn danh mục</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product->category_id) === (string)$category->id)>{{ $category->name_vi }}{{ $category->status ? '' : ' (ẩn)' }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="brand_id">Thương hiệu</label><select class="form-select" name="brand_id" id="brand_id"><option value="">Không có</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('brand_id', $product->brand_id) === (string)$brand->id)>{{ $brand->name_vi }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="price">Giá *</label><input class="form-control" type="number" min="0" step="0.01" name="price" id="price" value="{{ old('price', $product->price) }}" required></div>
        <div class="col-md-6"><label class="form-label" for="quantity">Số lượng *</label><input class="form-control" type="number" min="0" max="2147483647" step="1" name="quantity" id="quantity" value="{{ old('quantity', $product->quantity) }}" required></div>
        @foreach(['description_vi' => 'Mô tả tiếng Việt', 'description_en' => 'Mô tả tiếng Anh'] as $field => $label)
          <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $label }}</label><textarea class="form-control" rows="6" name="{{ $field }}" id="{{ $field }}" maxlength="60000">{{ old($field, $product->$field) }}</textarea></div>
        @endforeach
        @foreach(['status' => 'Hiển thị sản phẩm', 'is_featured' => 'Sản phẩm nổi bật'] as $field => $label)
          <div class="col-md-6"><input type="hidden" name="{{ $field }}" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="{{ $field }}" id="{{ $field }}" value="1" @checked(old($field, $product->$field))><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div></div>
        @endforeach
      </div>
      <hr class="my-4">
      <h2 class="h5">Ảnh sản phẩm</h2>
      <p class="text-muted">JPEG, PNG hoặc WebP, tối đa 2 MB/ảnh, tối đa 10 ảnh mới mỗi lần lưu. Thay đổi ảnh được áp dụng khi nhấn Lưu sản phẩm.</p>
      <label class="form-label" for="images">Thêm nhiều ảnh</label>
      <input class="form-control" type="file" id="images" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
      <div id="upload-preview" class="row g-3 mt-2"></div>
      <div class="my-3">
        <label class="form-label" for="primary_image">Ảnh đại diện</label>
        <select class="form-select" id="primary_image" name="primary_image">
          <option value="">Giữ ảnh đại diện hiện tại / tự chọn ảnh đầu tiên</option>
          @foreach($product->images as $image)
            <option value="existing:{{ $image->id }}" @selected(old('primary_image') === 'existing:'.$image->id)>Ảnh #{{ $image->id }}{{ $image->is_primary ? ' (đại diện hiện tại)' : '' }}</option>
          @endforeach
        </select>
      </div>
      <div class="row g-3">
        @foreach($product->images as $image)
          <div class="col-md-4">
            <div class="border rounded p-3 h-100">
              <img src="{{ route('product-images.show', $image) }}" alt="{{ $image->alt_text ?? $product->name_vi }}" class="img-fluid mb-2" style="height:150px;object-fit:contain;width:100%">
              <p class="mb-2">Ảnh #{{ $image->id }}</p>
              <label class="form-label" for="alt-{{ $image->id }}">Mô tả ảnh</label>
              <input class="form-control mb-2" id="alt-{{ $image->id }}" name="image_meta[{{ $image->id }}][alt_text]" maxlength="255" value="{{ old('image_meta.'.$image->id.'.alt_text', $image->alt_text) }}">
              <label class="form-label" for="sort-{{ $image->id }}">Thứ tự</label>
              <input class="form-control mb-2" type="number" min="0" max="2147483647" id="sort-{{ $image->id }}" name="image_meta[{{ $image->id }}][sort_order]" value="{{ old('image_meta.'.$image->id.'.sort_order', $image->sort_order) }}" required>
              <div class="form-check"><input class="form-check-input" type="checkbox" id="delete-{{ $image->id }}" name="delete_images[]" value="{{ $image->id }}" @checked(in_array($image->id, old('delete_images', [])))><label class="form-check-label text-danger" for="delete-{{ $image->id }}">Xóa ảnh này khi lưu</label></div>
            </div>
          </div>
        @endforeach
      </div>
      <p class="text-muted mt-3">Nếu form có lỗi, hãy chọn lại các file mới trước khi gửi lại. Nếu xóa ảnh đại diện, hệ thống tự chọn ảnh còn lại.</p>
      <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" @disabled($categories->isEmpty())><i class="fa-solid fa-save" aria-hidden="true"></i> Lưu sản phẩm</button><a class="btn btn-secondary" href="{{ route('admin.products') }}"><i class="fa-solid fa-times" aria-hidden="true"></i> Hủy</a></div>
    </form>
  </section>
@endsection

@section('scripts')
<script>
  const uploads = document.getElementById('images');
  const preview = document.getElementById('upload-preview');
  const primary = document.getElementById('primary_image');
  let previewUrls = [];
  uploads.addEventListener('change', function () {
    previewUrls.forEach(url => URL.revokeObjectURL(url));
    previewUrls = [];
    preview.replaceChildren();
    primary.querySelectorAll('[data-new-image]').forEach(option => option.remove());
    Array.from(uploads.files).forEach((file, index) => {
      const column = document.createElement('div');
      column.className = 'col-md-4';
      const label = document.createElement('label');
      label.className = 'form-label';
      label.htmlFor = 'new-alt-' + index;
      label.textContent = 'Ảnh mới ' + (index + 1) + ': ' + file.name;
      if (['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        const img = document.createElement('img');
        img.src = URL.createObjectURL(file);
        previewUrls.push(img.src);
        img.alt = file.name;
        img.style.cssText = 'height:120px;object-fit:contain;width:100%;display:block';
        column.append(img);
      }
      const alt = document.createElement('input');
      alt.className = 'form-control';
      alt.id = label.htmlFor;
      alt.name = 'new_alt[' + index + ']';
      alt.maxLength = 255;
      alt.placeholder = 'Mô tả ảnh (tùy chọn)';
      column.append(label, alt);
      preview.append(column);
      const option = new Option('Ảnh mới ' + (index + 1), 'new:' + index);
      option.dataset.newImage = '1';
      primary.append(option);
    });
  });
</script>
@endsection
