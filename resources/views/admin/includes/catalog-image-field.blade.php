<div class="col-12">
  <h2 class="h5 mt-3">Hình ảnh / Logo</h2>
  @if($record->image_src)
    <img src="{{ $record->image_src }}" alt="{{ $record->name_vi }}" class="rounded border mb-3" style="height:140px;max-width:100%;object-fit:contain">
  @endif
  <div class="row g-3">
    <div class="col-md-6">
      <label class="form-label" for="catalog-image">Upload ảnh mới</label>
      <input class="form-control" type="file" id="catalog-image" name="image" accept="image/jpeg,image/png,image/webp">
      <small class="text-muted">JPEG/PNG/WebP, tối đa 2 MB và 10.000 × 10.000 pixel. File mới ưu tiên hơn URL ảnh.</small>
      <img id="catalog-image-preview" class="rounded border mt-2" alt="Xem trước ảnh mới" hidden style="height:140px;max-width:100%;object-fit:contain">
    </div>
    <div class="col-md-6">
      <label class="form-label" for="image_url">Hoặc URL ảnh HTTP/HTTPS</label>
      <input class="form-control" type="url" id="image_url" name="image_url" maxlength="1024" value="{{ old('image_url', filter_var($record->image_url, FILTER_VALIDATE_URL) ? $record->image_url : '') }}" placeholder="https://...">
      <small class="text-muted">Để trống để giữ ảnh hiện tại. Nếu chọn xóa ảnh, hãy bỏ trống cả URL và file upload.</small>
    </div>
    @if($record->image_url)
      <div class="col-12"><div class="form-check"><input class="form-check-input" id="remove-image" type="checkbox" name="remove_image" value="1" @checked(old('remove_image'))><label class="form-check-label text-danger" for="remove-image">Xóa ảnh hiện tại khi lưu</label></div></div>
    @endif
  </div>
  <p class="text-muted mt-2">Ảnh chỉ thay đổi khi nhấn Lưu. Nếu form có lỗi, cần chọn lại file.</p>
</div>
@push('catalog-image-scripts')
<script>
  const imageInput = document.getElementById('catalog-image');
  const imagePreview = document.getElementById('catalog-image-preview');
  let catalogPreviewUrl;
  imageInput.addEventListener('change', function () {
    if (catalogPreviewUrl) URL.revokeObjectURL(catalogPreviewUrl);
    imagePreview.hidden = true;
    const file = this.files[0];
    if (file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      catalogPreviewUrl = URL.createObjectURL(file);
      imagePreview.src = catalogPreviewUrl;
      imagePreview.hidden = false;
    }
  });
</script>
@endpush
