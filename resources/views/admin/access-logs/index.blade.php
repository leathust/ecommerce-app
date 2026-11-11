@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Nhật ký quản trị</h1><span class="badge text-bg-primary">{{ number_format($audits->total()) }} sự kiện</span></div><p class="text-muted mb-0">Theo dõi ai đã thay đổi tài khoản, vai trò và permission vào thời điểm nào.</p></div>
  </div>

  <section class="m-card mb-4" aria-labelledby="audit-filter-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="audit-filter-title">Bộ lọc audit</h2><p class="m-card__subtitle">Lọc theo người thực hiện, hành động, đối tượng và khoảng ngày.</p></div></header>
    <form method="GET" action="{{ route('admin.access-logs.index') }}" class="row g-3 align-items-end">
      <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="audit-actor">Người thực hiện</label><select class="form-select @error('actor_id') is-invalid @enderror" id="audit-actor" name="actor_id"><option value="">Tất cả</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected(($filters['actor_id'] ?? '') == $actor->id)>{{ $actor->name }} — {{ $actor->email }}</option>@endforeach</select>@error('actor_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12 col-md-6 col-xl-3"><label class="form-label" for="audit-action">Hành động</label><select class="form-select @error('action') is-invalid @enderror" id="audit-action" name="action"><option value="">Tất cả</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ \App\Models\AdminAuditLog::ACTION_LABELS[$action] ?? $action }}</option>@endforeach</select>@error('action')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="audit-subject">Loại đối tượng</label><select class="form-select @error('subject_type') is-invalid @enderror" id="audit-subject" name="subject_type"><option value="">Tất cả</option>@foreach($subjectTypes as $key => $label)<option value="{{ $key }}" @selected(($filters['subject_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select>@error('subject_type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      @if(filled($filters['subject_id'] ?? null))<input type="hidden" name="subject_id" value="{{ $filters['subject_id'] }}">@endif
      <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="audit-date-from">Từ ngày</label><input class="form-control @error('date_from') is-invalid @enderror" id="audit-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}">@error('date_from')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="audit-date-to">Đến ngày</label><input class="form-control @error('date_to') is-invalid @enderror" id="audit-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}">@error('date_to')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
      <div class="col-12 d-flex flex-wrap gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Áp dụng</button><a class="btn btn-outline-secondary" href="{{ route('admin.access-logs.index') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Đặt lại</a>@if(filled($filters['subject_id'] ?? null))<span class="badge text-bg-info align-self-center">Đang lọc đối tượng #{{ $filters['subject_id'] }}</span>@endif</div>
    </form>
  </section>

  <section class="m-card" aria-labelledby="audit-list-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="audit-list-title">Dòng thời gian thay đổi</h2><p class="m-card__subtitle">Sự kiện mới nhất được hiển thị trước.</p></div></header>
    @if($audits->isEmpty())
      <div class="text-center py-5"><i class="fa-solid fa-clock-rotate-left fs-1 text-muted" aria-hidden="true"></i><h3 class="h5 mt-3">Không tìm thấy sự kiện</h3><p class="text-muted mb-0">Hãy thay đổi bộ lọc hoặc quay lại sau khi có thao tác IAM.</p></div>
    @else
      <div class="table-responsive"><table class="m-table table align-middle"><thead><tr><th>Thời gian</th><th>Người thực hiện</th><th>Hành động</th><th>Đối tượng</th><th>Nguồn</th><th></th></tr></thead><tbody>
        @foreach($audits as $audit)
          <tr>
            <td class="text-nowrap"><time datetime="{{ $audit->created_at?->toIso8601String() }}">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</time></td>
            <td>@if($audit->actor)<strong>{{ $audit->actor->display_name }}</strong><div class="small text-muted">{{ $audit->actor->email }}</div>@else<span class="text-muted">Tài khoản đã xóa / hệ thống</span>@endif</td>
            <td><strong>{{ $audit->action_label }}</strong><div><code>{{ $audit->action }}</code></div></td>
            <td><span class="badge text-bg-light border">{{ $audit->subject_label }} #{{ $audit->subject_id ?? '—' }}</span>@if($audit->subject)<div class="small text-muted mt-1">{{ $audit->subject->name ?? $audit->subject->display_name ?? '' }}</div>@endif</td>
            <td><span class="small">{{ $audit->ip_address ?: 'Không ghi nhận IP' }}</span></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.access-logs.show', $audit) }}">Chi tiết</a></td>
          </tr>
        @endforeach
      </tbody></table></div>
      <div class="mt-4">{{ $audits->links() }}</div>
    @endif
  </section>
@endsection
