@extends('admin.layout')

@section('content')
  @include('admin.users.partials.alerts')

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Tài khoản &amp; quyền</h1><span class="badge text-bg-primary">{{ number_format($users->total()) }} tài khoản</span></div>
      <p class="text-muted mb-0">Tra cứu tài khoản có quyền truy cập khu vực quản trị.</p>
    </div>
    @can('create', \App\Models\User::class)<a class="btn btn-primary" href="{{ route('admin.users.create') }}"><i class="fa-solid fa-user-plus me-1" aria-hidden="true"></i>Tạo tài khoản</a>@endcan
  </div>

  <section class="m-card mb-4" aria-labelledby="admin-user-filter-title">
    <header class="m-card__header">
      <div><h2 class="m-card__title" id="admin-user-filter-title">Tìm kiếm và lọc</h2><p class="m-card__subtitle">Có thể kết hợp từ khóa, trạng thái và vai trò quản trị.</p></div>
    </header>
    <form action="{{ route('admin.users.index') }}" method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-xl-5">
        <label class="form-label" for="admin-user-search">Tìm kiếm</label>
        <input class="form-control" id="admin-user-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Tên, username, email hoặc điện thoại...">
      </div>
      <div class="col-12 col-md-4 col-xl-2">
        <label class="form-label" for="admin-user-status">Trạng thái</label>
        <select class="form-select" id="admin-user-status" name="status">
          <option value="">Tất cả</option>
          @foreach($statuses as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-4 col-xl-3">
        <label class="form-label" for="admin-user-role">Vai trò</label>
        <select class="form-select" id="admin-user-role" name="role_id">
          <option value="">Tất cả role quản trị</option>
          @foreach($roles as $role)
            <option value="{{ $role->id }}" @selected((string) ($filters['role_id'] ?? '') === (string) $role->id)>{{ $role->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-4 col-xl-2 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Lọc</button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.users.index') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Đặt lại</a>
      </div>
    </form>
  </section>

  <section class="m-card" aria-labelledby="admin-user-list-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="admin-user-list-title">Tài khoản quản trị</h2><p class="m-card__subtitle">Mới nhất trước, 15 tài khoản mỗi trang; tài khoản khách hàng không xuất hiện tại đây.</p></div></header>
    @if($users->isEmpty())
      <div class="text-center py-5"><i class="fa-solid fa-user-shield fs-1 text-muted" aria-hidden="true"></i><h3 class="h5 mt-3">Không tìm thấy tài khoản</h3><p class="text-muted mb-0">Hãy thử thay đổi bộ lọc hoặc từ khóa.</p></div>
    @else
      <div class="table-responsive">
        <table class="m-table table align-middle">
          <thead><tr><th>ID</th><th>Tài khoản</th><th>Liên hệ</th><th>Vai trò</th><th>Trạng thái</th><th>Đăng nhập gần nhất</th><th>Thao tác</th></tr></thead>
          <tbody>
            @foreach($users as $managedUser)
              @php
                $statusClass = match($managedUser->status) {
                  'active' => 'text-bg-success',
                  'blocked' => 'text-bg-danger',
                  default => 'text-bg-secondary',
                };
              @endphp
              <tr>
                <td>#{{ $managedUser->id }}</td>
                <td><strong>{{ $managedUser->display_name }}</strong><div class="text-muted">{{ '@'.$managedUser->username }}</div></td>
                <td><a href="mailto:{{ $managedUser->email }}">{{ $managedUser->email }}</a>@if($managedUser->phone)<div class="text-muted">{{ $managedUser->phone }}</div>@endif</td>
                <td><div class="d-flex flex-wrap gap-1">@foreach($managedUser->roles as $role)<span class="badge {{ $role->name === \App\Models\Role::SUPER_ADMIN ? 'text-bg-dark' : 'text-bg-primary' }}">{{ $role->name }}</span>@endforeach</div></td>
                <td><span class="badge {{ $statusClass }}">{{ $statuses[$managedUser->status] ?? $managedUser->status }}</span>@if($managedUser->must_change_password)<div class="small text-warning mt-1">Cần đổi mật khẩu</div>@endif</td>
                <td>@if($managedUser->last_login_at)<time datetime="{{ $managedUser->last_login_at->toIso8601String() }}">{{ $managedUser->last_login_at->format('d/m/Y H:i') }}</time>@else<span class="text-muted">Chưa ghi nhận</span>@endif</td>
                <td><div class="d-flex gap-1"><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.show', $managedUser) }}"><i class="fa-regular fa-eye me-1" aria-hidden="true"></i>Xem</a>@can('update', $managedUser)<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.users.edit', $managedUser) }}"><i class="fa-solid fa-pen" aria-hidden="true"></i><span class="visually-hidden">Sửa</span></a>@endcan</div></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-4">{{ $users->links() }}</div>
    @endif
  </section>
@endsection
