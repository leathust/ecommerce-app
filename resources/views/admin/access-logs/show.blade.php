@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.access-logs.index') }}">Nhật ký quản trị</a></li><li class="breadcrumb-item active" aria-current="page">#{{ $audit->id }}</li></ol></nav><h1 class="h3 mb-1">{{ $audit->action_label }}</h1><p class="text-muted mb-0"><code>{{ $audit->action }}</code> · {{ $audit->subject_label }} #{{ $audit->subject_id ?? '—' }}</p></div>
    <a class="btn btn-outline-secondary" href="{{ route('admin.access-logs.index') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-6"><section class="m-card h-100"><header class="m-card__header"><div><h2 class="m-card__title">Ngữ cảnh sự kiện</h2><p class="m-card__subtitle">Thông tin định danh và thời điểm ghi audit.</p></div></header><dl class="row mb-0"><dt class="col-sm-4">Người thực hiện</dt><dd class="col-sm-8">@if($audit->actor)<strong>{{ $audit->actor->display_name }}</strong><div class="text-muted">{{ $audit->actor->email }}</div>@else<span class="text-muted">Tài khoản đã xóa / hệ thống</span>@endif</dd><dt class="col-sm-4">Thời gian</dt><dd class="col-sm-8"><time datetime="{{ $audit->created_at?->toIso8601String() }}">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</time></dd><dt class="col-sm-4">Đối tượng</dt><dd class="col-sm-8">{{ $audit->subject_label }} #{{ $audit->subject_id ?? '—' }}@if(!$audit->subject)<div class="small text-muted">Đối tượng đã bị xóa hoặc không còn truy cập được.</div>@endif</dd><dt class="col-sm-4">IP</dt><dd class="col-sm-8">{{ $audit->ip_address ?: 'Không ghi nhận' }}</dd></dl></section></div>
    <div class="col-12 col-xl-6"><section class="m-card h-100"><header class="m-card__header"><div><h2 class="m-card__title">Thiết bị</h2><p class="m-card__subtitle">User agent được giới hạn độ dài khi ghi.</p></div></header><p class="text-break mb-0">{{ $audit->user_agent ?: 'Không ghi nhận user agent.' }}</p></section></div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-6"><section class="m-card h-100"><header class="m-card__header"><div><h2 class="m-card__title">Trước thay đổi</h2><p class="m-card__subtitle">Payload đã qua allowlist và che dữ liệu nhạy cảm.</p></div></header>@include('admin.access-logs.partials.payload', ['payload' => $beforeValues])</section></div>
    <div class="col-12 col-xl-6"><section class="m-card h-100"><header class="m-card__header"><div><h2 class="m-card__title">Sau thay đổi</h2><p class="m-card__subtitle">Giá trị được escape khi hiển thị, không cho phép HTML thực thi.</p></div></header>@include('admin.access-logs.partials.payload', ['payload' => $afterValues])</section></div>
  </div>
@endsection
