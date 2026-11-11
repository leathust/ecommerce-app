@extends('admin.layout')

@section('content')
  @php
    $statusClass = match($managedUser->status) {
      'active' => 'text-bg-success',
      'blocked' => 'text-bg-danger',
      default => 'text-bg-secondary',
    };
  @endphp

  @include('admin.users.partials.alerts')

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Tài khoản &amp; quyền</a></li><li class="breadcrumb-item active" aria-current="page">#{{ $managedUser->id }}</li></ol></nav>
      <h1 class="h3 mb-1">{{ $managedUser->display_name }}</h1>
      <p class="text-muted mb-0">Hồ sơ quản trị, vai trò và quyền hiệu lực.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      @can('update', $managedUser)<a class="btn btn-primary" href="{{ route('admin.users.edit', $managedUser) }}"><i class="fa-solid fa-pen me-1" aria-hidden="true"></i>Cập nhật</a>@endcan
      @can('delete', $managedUser)
        <form method="POST" action="{{ route('admin.users.destroy', $managedUser) }}" data-confirm-title="Xóa hoặc khóa tài khoản" data-confirm-message="Xóa tài khoản nếu không có dữ liệu nghiệp vụ? Nếu có lịch sử liên quan, hệ thống sẽ khóa tài khoản thay vì xóa." data-confirm-button="Tiếp tục">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger" type="submit"><i class="fa-solid fa-user-slash me-1" aria-hidden="true"></i>Xóa / khóa</button>
        </form>
      @endcan
      <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
      <section class="m-card h-100" aria-labelledby="managed-user-profile-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="managed-user-profile-title">Thông tin tài khoản</h2><p class="m-card__subtitle">Thông tin nhận diện và trạng thái bảo mật.</p></div><span class="badge {{ $statusClass }}">{{ $statuses[$managedUser->status] ?? $managedUser->status }}</span></header>
        <dl class="row mb-0">
          <dt class="col-sm-4 py-2">Họ và tên</dt><dd class="col-sm-8 py-2">{{ $managedUser->display_name }}</dd>
          <dt class="col-sm-4 py-2">Username</dt><dd class="col-sm-8 py-2">{{ '@'.$managedUser->username }}</dd>
          <dt class="col-sm-4 py-2">Email</dt><dd class="col-sm-8 py-2"><a href="mailto:{{ $managedUser->email }}">{{ $managedUser->email }}</a></dd>
          <dt class="col-sm-4 py-2">Điện thoại</dt><dd class="col-sm-8 py-2">{{ $managedUser->phone ?: 'Chưa cập nhật' }}</dd>
          <dt class="col-sm-4 py-2">Đổi mật khẩu</dt><dd class="col-sm-8 py-2">@if($managedUser->must_change_password)<span class="badge text-bg-warning">Bắt buộc ở lần đăng nhập tiếp theo</span>@else<span class="text-muted">Không yêu cầu</span>@endif</dd>
        </dl>
      </section>
    </div>
    <div class="col-12 col-xl-5">
      <section class="m-card h-100" aria-labelledby="managed-user-time-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="managed-user-time-title">Thông tin hệ thống</h2><p class="m-card__subtitle">Các mốc hoạt động của tài khoản.</p></div></header>
        <dl class="mb-0">
          <dt>Ngày tạo</dt><dd class="mt-1 mb-3"><time datetime="{{ $managedUser->created_at?->toIso8601String() }}">{{ $managedUser->created_at?->format('d/m/Y H:i:s') ?: 'Không xác định' }}</time></dd>
          <dt>Cập nhật gần nhất</dt><dd class="mt-1 mb-3"><time datetime="{{ $managedUser->updated_at?->toIso8601String() }}">{{ $managedUser->updated_at?->format('d/m/Y H:i:s') ?: 'Không xác định' }}</time></dd>
          <dt>Đăng nhập gần nhất</dt><dd class="mt-1 mb-3">@if($managedUser->last_login_at)<time datetime="{{ $managedUser->last_login_at->toIso8601String() }}">{{ $managedUser->last_login_at->format('d/m/Y H:i:s') }}</time>@else<span class="text-muted">Chưa ghi nhận</span>@endif</dd>
          <dt>ID tài khoản</dt><dd class="mt-1 mb-0">#{{ $managedUser->id }}</dd>
        </dl>
      </section>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-xl-5">
      <section class="m-card h-100" aria-labelledby="managed-user-role-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="managed-user-role-title">Vai trò</h2><p class="m-card__subtitle">{{ $managedUser->roles->count() }} role đang được gán.</p></div></header>
        <div class="d-flex flex-column gap-2">
          @foreach($managedUser->roles as $role)
            <div class="border rounded p-3 d-flex justify-content-between align-items-start gap-3">
              <div><strong>{{ $role->name }}</strong><div class="small text-muted">{{ $role->description ?: 'Không có mô tả' }}</div></div>
              <div class="d-flex gap-1">@if($role->is_system)<span class="badge text-bg-secondary">System</span>@endif<span class="badge {{ $role->is_admin ? 'text-bg-primary' : 'text-bg-light' }}">{{ $role->permissions->count() }} quyền</span></div>
            </div>
          @endforeach
        </div>
      </section>
    </div>
    <div class="col-12 col-xl-7">
      <section class="m-card h-100" aria-labelledby="managed-user-permission-title">
        <header class="m-card__header"><div><h2 class="m-card__title" id="managed-user-permission-title">Quyền hiệu lực</h2><p class="m-card__subtitle">Hợp quyền từ tất cả role: {{ $effectivePermissions->count() }} permission.</p></div></header>
        @if($permissionGroups->isEmpty())
          <p class="text-muted mb-0">Tài khoản chưa có permission hiệu lực.</p>
        @else
          <div class="row g-3">
            @foreach($permissionGroups as $module => $permissions)
              <div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><strong class="text-uppercase small">{{ $module }}</strong><div class="d-flex flex-wrap gap-1 mt-2">@foreach($permissions as $permission)<span class="badge text-bg-light border">{{ $permission }}</span>@endforeach</div></div></div>
            @endforeach
          </div>
        @endif
      </section>
    </div>
  </div>

  <section class="m-card" aria-labelledby="managed-user-audit-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="managed-user-audit-title">Thay đổi gần nhất</h2><p class="m-card__subtitle">Tối đa 10 sự kiện audit tác động trực tiếp đến tài khoản này.</p></div>@can('permissions.manage')<a class="btn btn-sm btn-outline-primary" href="{{ route('admin.access-logs.index', ['subject_type' => 'user', 'subject_id' => $managedUser->id]) }}">Xem toàn bộ</a>@endcan</header>
    @if($recentAudits->isEmpty())
      <div class="text-center py-4"><i class="fa-solid fa-clock-rotate-left fs-2 text-muted" aria-hidden="true"></i><p class="text-muted mt-2 mb-0">Chưa có lịch sử thay đổi.</p></div>
    @else
      <div class="table-responsive"><table class="m-table table align-middle"><thead><tr><th>Thời gian</th><th>Hành động</th><th>Người thực hiện</th><th>Thay đổi</th></tr></thead><tbody>
        @foreach($recentAudits as $audit)
          <tr>
            <td><time datetime="{{ $audit->created_at?->toIso8601String() }}">{{ $audit->created_at?->format('d/m/Y H:i:s') }}</time></td>
            <td><code>{{ $audit->action }}</code></td>
            <td>@if($audit->actor)<strong>{{ $audit->actor->display_name }}</strong><div class="small text-muted">{{ $audit->actor->email }}</div>@else<span class="text-muted">Tài khoản đã xóa / hệ thống</span>@endif</td>
            <td><span class="badge text-bg-light border">{{ count($audit->before_values ?? []) }} trước</span><span class="mx-1" aria-hidden="true">→</span><span class="badge text-bg-light border">{{ count($audit->after_values ?? []) }} sau</span>@can('permissions.manage')<a class="btn btn-sm btn-link" href="{{ route('admin.access-logs.show', $audit) }}">Chi tiết</a>@endcan</td>
          </tr>
        @endforeach
      </tbody></table></div>
    @endif
  </section>
@endsection
