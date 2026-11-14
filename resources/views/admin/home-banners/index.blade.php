@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Banner trang chủ</h1><span class="badge text-bg-primary">{{ number_format($banners->total()) }} banner</span></div>
      <p class="text-muted mb-0">Quản lý Hero và khối quảng bá theo ngôn ngữ, lịch hiển thị và thứ tự.</p>
    </div>
    @can('banners.create')<a class="btn btn-primary" href="{{ route('admin.home-banners.create') }}"><i class="fa-solid fa-plus me-1"></i>Thêm banner</a>@endcan
  </div>

  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif

  <section class="m-card">
    <form method="GET" action="{{ route('admin.home-banners.index') }}" class="row g-2 mb-4">
      <div class="col-lg-6"><label class="visually-hidden" for="banner-q">Tìm banner</label><input class="form-control" id="banner-q" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm theo tiêu đề..."></div>
      <div class="col-sm-4 col-lg-2"><select class="form-select" name="placement" aria-label="Vị trí"><option value="">Mọi vị trí</option><option value="hero" @selected(($filters['placement'] ?? '') === 'hero')>Hero</option><option value="discount" @selected(($filters['placement'] ?? '') === 'discount')>Quảng bá</option></select></div>
      <div class="col-sm-4 col-lg-2"><select class="form-select" name="status" aria-label="Trạng thái"><option value="">Mọi trạng thái</option><option value="active" @selected(($filters['status'] ?? '') === 'active')>Đang hiển thị</option><option value="scheduled" @selected(($filters['status'] ?? '') === 'scheduled')>Đã lên lịch</option><option value="expired" @selected(($filters['status'] ?? '') === 'expired')>Đã hết hạn</option><option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Đã tắt</option></select></div>
      <div class="col-sm-4 col-lg-2 d-flex gap-2"><button class="btn btn-primary flex-grow-1"><i class="fa-solid fa-search"></i> Tìm</button><a class="btn btn-outline-secondary" href="{{ route('admin.home-banners.index') }}" aria-label="Đặt lại"><i class="fa-solid fa-rotate-left"></i></a></div>
    </form>

    <div class="table-responsive"><table class="m-table table align-middle">
      <thead><tr><th>Banner</th><th>Vị trí</th><th>Lịch hiển thị</th><th>Thứ tự</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
      <tbody>
        @forelse($banners as $banner)
          @php
            $state = ! $banner->status ? ['Đã tắt', 'text-bg-secondary'] : (($banner->starts_at && $banner->starts_at->gt($now)) ? ['Đã lên lịch', 'text-bg-info'] : (($banner->ends_at && $banner->ends_at->lte($now)) ? ['Đã hết hạn', 'text-bg-warning'] : ['Đang hiển thị', 'text-bg-success']));
          @endphp
          <tr>
            <td><div class="d-flex align-items-center gap-3">@if($banner->image_url)<img src="{{ $banner->image_url }}" alt="" width="80" height="56" class="rounded border" style="object-fit:cover">@endif<div><strong>{{ $banner->title_vi }}</strong><div class="text-muted small">{{ $banner->title_en }}</div></div></div></td>
            <td><span class="badge {{ $banner->placement === 'hero' ? 'text-bg-primary' : 'text-bg-dark' }}">{{ $banner->placement === 'hero' ? 'Hero' : 'Quảng bá' }}</span><div class="small text-muted mt-1">Theme: {{ $banner->theme }}</div></td>
            <td class="small"><div>Từ: {{ $banner->starts_at?->format('d/m/Y H:i') ?? 'Ngay lập tức' }}</div><div>Đến: {{ $banner->ends_at?->format('d/m/Y H:i') ?? 'Không giới hạn' }}</div></td>
            <td>{{ $banner->sort_order }}</td><td><span class="badge {{ $state[1] }}">{{ $state[0] }}</span></td>
            <td><div class="d-flex gap-2">
              @can('banners.update')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.home-banners.edit', $banner) }}"><i class="fa-solid fa-pen"></i> Sửa</a>@endcan
              @can('banners.delete')<form method="POST" action="{{ route('admin.home-banners.destroy', $banner) }}" data-confirm-title="Xóa banner" data-confirm-message="Xóa vĩnh viễn banner này?" data-confirm-button="Xóa banner">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i> Xóa</button></form>@endcan
            </div></td>
          </tr>
        @empty<tr><td colspan="6" class="text-center py-5 text-muted">Chưa có banner phù hợp.</td></tr>@endforelse
      </tbody>
    </table></div>
    <div class="mt-4">{{ $banners->links() }}</div>
  </section>
@endsection
