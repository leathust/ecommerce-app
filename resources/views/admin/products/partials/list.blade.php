@can('products.delete')
  <div class="d-flex flex-wrap align-items-center gap-2 mb-3 product-bulk-toolbar">
    <button type="button" class="btn btn-outline-danger" id="bulk-delete-open" disabled><i class="fa-solid fa-trash me-1"></i>Xóa (<span id="selected-product-count">0</span>)</button>
    <button type="button" class="btn btn-link text-decoration-none" id="clear-product-selection" disabled>Bỏ chọn</button>
    <span class="small text-muted d-none" id="current-page-selection-note">Chỉ chọn các sản phẩm trên trang hiện tại</span>
  </div>
@endcan
<div class="table-responsive"><table class="m-table table align-middle">
  <thead><tr>
    @can('products.delete')<th style="width:42px"><input class="form-check-input" type="checkbox" id="select-all-products" aria-label="Chọn tất cả sản phẩm trên trang"></th>@endcan
    <th>ID / SKU</th><th>Sản phẩm</th><th>Danh mục / Thương hiệu</th><th>Giá</th><th>Tồn kho</th><th>Trạng thái</th><th>Thao tác</th>
  </tr></thead>
  <tbody>@forelse($products as $product)
    <tr>
      @can('products.delete')<td><input class="form-check-input product-checkbox" type="checkbox" value="{{ $product->id }}" aria-label="Chọn sản phẩm {{ $product->name_vi }}"></td>@endcan
      <td>#{{ $product->id }}<div>{{ $product->sku }}</div></td>
      <td>
        @if($product->images->isNotEmpty())<img src="{{ route('product-images.show', $product->images->first()) }}" alt="{{ $product->images->first()->alt_text ?? $product->name_vi }}" width="64" height="64" class="rounded mb-2" style="object-fit:contain">@endif
        <div><strong>{{ $product->name_vi }}</strong></div><div class="text-muted">{{ $product->name_en }}</div>@if($product->is_featured)<span class="badge text-bg-warning">Nổi bật</span>@endif
      </td>
      <td>{{ $product->category?->name_vi }}<div class="text-muted">{{ $product->brand?->name_vi ?? '—' }}</div></td>
      <td>{{ $product->price }}</td><td>{{ $product->quantity }}</td>
      <td><span class="badge {{ $product->status ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $product->status ? 'Hiển thị' : 'Ẩn' }}</span></td>
      <td><div class="d-flex gap-2">
        @can('products.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.products.edit', $product) }}">Sửa</a>@endcan
        @can('products.delete')<form method="POST" action="{{ route('admin.products.destroy', $product) }}" data-confirm-title="Xóa sản phẩm" data-confirm-message="Xóa vĩnh viễn sản phẩm này? Ảnh và liên kết yêu thích sẽ bị xóa; lịch sử đơn hàng vẫn được giữ." data-confirm-button="Xóa sản phẩm">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Xóa</button></form>@endcan
      </div></td>
    </tr>
  @empty
    <tr><td colspan="{{ auth()->user()->can('products.delete') ? 8 : 7 }}" class="text-center py-5 text-muted">Chưa có sản phẩm phù hợp.</td></tr>
  @endforelse</tbody>
</table></div>
<div class="mt-4">{{ $products->links() }}</div>
