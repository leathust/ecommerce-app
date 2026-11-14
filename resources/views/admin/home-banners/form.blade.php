@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h1 class="h3 mb-1">{{ $homeBanner->exists ? 'Cập nhật banner' : 'Thêm banner' }}</h1><p class="text-muted mb-0">Nội dung storefront hỗ trợ tiếng Việt và tiếng Anh.</p></div><a class="btn btn-outline-secondary" href="{{ route('admin.home-banners.index') }}"><i class="fa-solid fa-arrow-left"></i> Danh sách</a></div>
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ $homeBanner->exists ? route('admin.home-banners.update', $homeBanner) : route('admin.home-banners.store') }}">
    @csrf @if($homeBanner->exists) @method('PUT') @endif
    <section class="m-card mb-4"><h2 class="h5 mb-3">Nội dung và vị trí</h2><div class="row g-3">
      <div class="col-md-4"><label class="form-label" for="placement">Vị trí *</label><select class="form-select" id="placement" name="placement" required><option value="hero" @selected(old('placement', $homeBanner->placement) === 'hero')>Hero</option><option value="discount" @selected(old('placement', $homeBanner->placement) === 'discount')>Khối quảng bá</option></select></div>
      <div class="col-md-4"><label class="form-label" for="theme">Theme *</label><select class="form-select" id="theme" name="theme" required>@foreach(\App\Models\HomeBanner::THEMES as $theme)<option value="{{ $theme }}" @selected(old('theme', $homeBanner->theme) === $theme)>{{ ucfirst($theme) }}</option>@endforeach</select></div>
      <div class="col-md-4"><label class="form-label" for="sort_order">Thứ tự *</label><input class="form-control" type="number" min="0" max="100000" id="sort_order" name="sort_order" value="{{ old('sort_order', $homeBanner->sort_order ?? 0) }}" required></div>
      @foreach(['vi' => 'Tiếng Việt', 'en' => 'English'] as $locale => $label)
        <div class="col-12"><h3 class="h6 border-bottom pb-2 mb-0">{{ $label }}</h3></div>
        <div class="col-md-6"><label class="form-label" for="title_{{ $locale }}">Tiêu đề *</label><input class="form-control" id="title_{{ $locale }}" name="title_{{ $locale }}" maxlength="255" value="{{ old('title_'.$locale, $homeBanner->{'title_'.$locale}) }}" required></div>
        <div class="col-md-3"><label class="form-label" for="eyebrow_{{ $locale }}">Nhãn nhỏ</label><input class="form-control" id="eyebrow_{{ $locale }}" name="eyebrow_{{ $locale }}" maxlength="120" value="{{ old('eyebrow_'.$locale, $homeBanner->{'eyebrow_'.$locale}) }}"></div>
        <div class="col-md-3"><label class="form-label" for="button_{{ $locale }}">Nhãn nút</label><input class="form-control" id="button_{{ $locale }}" name="button_{{ $locale }}" maxlength="80" value="{{ old('button_'.$locale, $homeBanner->{'button_'.$locale}) }}"></div>
        <div class="col-12"><label class="form-label" for="description_{{ $locale }}">Mô tả</label><textarea class="form-control" id="description_{{ $locale }}" name="description_{{ $locale }}" rows="2" maxlength="1000">{{ old('description_'.$locale, $homeBanner->{'description_'.$locale}) }}</textarea></div>
      @endforeach
    </div></section>

    <section class="m-card mb-4"><h2 class="h5 mb-3">Media, liên kết và lịch</h2><div class="row g-3">
      <div class="col-md-6"><label class="form-label" for="image_url">URL ảnh HTTPS/HTTP</label><input class="form-control" type="url" id="image_url" name="image_url" maxlength="1024" value="{{ old('image_url', $homeBanner->image_url) }}" placeholder="https://cdn.example.com/banner.webp"><div class="form-text">Để trống sẽ dùng ảnh placeholder an toàn.</div></div>
      <div class="col-md-6"><label class="form-label" for="link_url">Liên kết nội bộ</label><input class="form-control" id="link_url" name="link_url" maxlength="512" value="{{ old('link_url', $homeBanner->link_url) }}" placeholder="shop?category=dien-thoai"><div class="form-text">Chỉ nhận shop, shop?category=slug, shop?brand=slug hoặc products/slug.</div></div>
      <div class="col-md-6"><label class="form-label" for="starts_at">Bắt đầu</label><input class="form-control" type="datetime-local" id="starts_at" name="starts_at" value="{{ old('starts_at', $homeBanner->starts_at?->format('Y-m-d\TH:i')) }}"></div>
      <div class="col-md-6"><label class="form-label" for="ends_at">Kết thúc</label><input class="form-control" type="datetime-local" id="ends_at" name="ends_at" value="{{ old('ends_at', $homeBanner->ends_at?->format('Y-m-d\TH:i')) }}"></div>
      <div class="col-12"><input type="hidden" name="status" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="status" name="status" value="1" @checked(old('status', $homeBanner->status ?? true))><label class="form-check-label" for="status">Cho phép xuất bản theo lịch</label></div></div>
      @if(old('image_url', $homeBanner->image_url))<div class="col-12"><img src="{{ old('image_url', $homeBanner->image_url) }}" alt="Xem trước banner" class="img-fluid rounded border" style="max-height:240px;object-fit:cover"></div>@endif
    </div></section>
    <div class="d-flex gap-2"><button class="btn btn-primary"><i class="fa-solid fa-save"></i> Lưu banner</button><a class="btn btn-secondary" href="{{ route('admin.home-banners.index') }}">Hủy</a></div>
  </form>
@endsection
