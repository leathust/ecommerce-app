@extends('admin.layout')

@section('content')
  @if(session('success'))<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>@endif
  @error('delete')<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ $message }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div>@enderror

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div><div class="d-flex flex-wrap align-items-center gap-2 mb-1"><h1 class="h3 mb-0">Vai trò quản trị</h1><span class="badge text-bg-primary">{{ number_format($roles->total()) }} vai trò</span></div><p class="text-muted mb-0">Super Admin có thể cấu hình role hệ thống và role tùy chỉnh; riêng super_admin được bảo vệ tuyệt đối.</p></div>
    @can('create', \App\Models\Role::class)<a class="btn btn-primary" href="{{ route('admin.roles.create') }}"><i class="fa-solid fa-plus me-1" aria-hidden="true"></i>Tạo vai trò</a>@endcan
  </div>

  <section class="m-card" aria-labelledby="role-list-title">
    <header class="m-card__header align-items-start gap-3">
      <div><h2 class="m-card__title" id="role-list-title">Danh sách role</h2><p class="m-card__subtitle">Chỉ super_admin được bảo vệ tuyệt đối; role khác có thể xóa khi chưa được gán cho tài khoản.</p></div>
      <form method="GET" action="{{ route('admin.roles.index') }}" class="d-flex flex-wrap justify-content-end gap-2">
        <div style="min-width:260px"><label class="visually-hidden" for="role-search">Tìm theo tên hoặc mô tả role</label><input class="form-control @error('q') is-invalid @enderror" id="role-search" type="search" name="q" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="Tìm tên hoặc mô tả role...">@error('q')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1" aria-hidden="true"></i>Tìm</button>
        @if(filled($filters['q'] ?? null))<a class="btn btn-outline-secondary" href="{{ route('admin.roles.index') }}"><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Đặt lại</a>@endif
      </form>
    </header>
    @if($roles->isEmpty())
      <div class="text-center py-5"><i class="fa-solid fa-shield-halved fs-1 text-muted" aria-hidden="true"></i><h3 class="h5 mt-3">Không tìm thấy vai trò</h3></div>
    @else
      <div class="table-responsive">
        <table class="m-table table align-middle">
          <thead><tr><th>Vai trò</th><th>Loại</th><th>Phạm vi</th><th>Tài khoản</th><th>Permission</th><th>Thao tác</th></tr></thead>
          <tbody>
            @foreach($roles as $role)
              <tr>
                <td><strong>{{ $role->name }}</strong><div class="small text-muted">{{ $role->description ?: 'Chưa có mô tả' }}</div></td>
                <td><span class="badge {{ $role->isReservedSystemRole() ? 'text-bg-secondary' : 'text-bg-info' }}">{{ $role->isReservedSystemRole() ? 'System' : 'Custom' }}</span></td>
                <td><span class="badge {{ $role->is_admin ? 'text-bg-primary' : 'text-bg-light border' }}">{{ $role->is_admin ? 'Admin' : 'Thông thường' }}</span></td>
                <td>{{ number_format($role->users_count) }}</td>
                <td>{{ number_format($role->permissions_count) }}</td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    @can('update', $role)
                      <a class="btn btn-sm btn-primary" href="{{ route('admin.roles.edit', $role) }}"><i class="fa-solid fa-pen-to-square me-1" aria-hidden="true"></i>Sửa &amp; phân quyền</a>
                    @else
                      <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="{{ $role->isProtectedSuperAdminRole() ? 'Role super_admin được bảo vệ tuyệt đối' : 'Chỉ Super Admin được sửa role và permission' }}"><i class="fa-solid fa-lock me-1" aria-hidden="true"></i>{{ $role->isProtectedSuperAdminRole() ? 'Bảo vệ tuyệt đối' : 'Không có quyền sửa' }}</button>
                    @endcan
                    @can('permissions.manage')<a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.access-logs.index', ['subject_type' => 'role', 'subject_id' => $role->id]) }}"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Lịch sử</a>@endcan
                    @can('delete', $role)
                      @if($role->users_count === 0)
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" data-confirm-title="Xóa vai trò" data-confirm-message="Xóa vai trò {{ $role->name }}? Permission đang gán cho role cũng sẽ được gỡ." data-confirm-button="Xóa vai trò">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit"><i class="fa-solid fa-trash me-1" aria-hidden="true"></i>Xóa</button></form>
                      @else
                        <button class="btn btn-sm btn-outline-secondary" type="button" disabled title="Hãy chuyển {{ $role->users_count }} tài khoản sang role khác trước"><i class="fa-solid fa-user-lock me-1" aria-hidden="true"></i>Đang sử dụng</button>
                      @endif
                    @endcan
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="mt-4">{{ $roles->links() }}</div>
    @endif
  </section>
@endsection
