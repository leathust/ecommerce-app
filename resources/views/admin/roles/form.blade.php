@extends('admin.layout')

@section('content')
  @php
    $editing = $role->exists;
    $systemRole = $editing && $role->isReservedSystemRole();
    $selectedPermissionIds = collect(old('permission_ids', $editing ? $role->permissions->pluck('id')->all() : []))
      ->map(fn($id) => (int) $id);
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.roles.index') }}">Vai trò</a></li><li class="breadcrumb-item active" aria-current="page">{{ $editing ? 'Cập nhật' : 'Tạo mới' }}</li></ol></nav>
      <h1 class="h3 mb-1">{{ $editing ? 'Cập nhật vai trò' : 'Tạo vai trò quản trị' }}</h1>
      <p class="text-muted mb-0">Thông tin role và ma trận permission được lưu cùng lúc trong một transaction.</p>
    </div>
    <div class="d-flex gap-2">
      @if($editing) @can('permissions.manage')<a class="btn btn-outline-primary" href="{{ route('admin.access-logs.index', ['subject_type' => 'role', 'subject_id' => $role->id]) }}"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Lịch sử</a>@endcan @endif
      <a class="btn btn-outline-secondary" href="{{ route('admin.roles.index') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Danh sách</a>
    </div>
  </div>

  <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" id="role-form">
    @csrf
    @if($editing) @method('PUT') @endif

    <section class="m-card mb-4">
        <header class="m-card__header"><div><h2 class="m-card__title">Thông tin role</h2><p class="m-card__subtitle">{{ $systemRole ? 'Role hệ thống cho phép chỉnh mô tả và permission nhưng tên luôn được giữ cố định.' : 'Tên được chuẩn hóa thành chữ thường và dấu gạch dưới; role mới luôn thuộc phạm vi quản trị.' }}</p></div><span class="badge {{ $systemRole ? 'text-bg-secondary' : 'text-bg-info' }}">{{ $systemRole ? 'System admin role' : 'Custom admin role' }}</span></header>
        <div class="mb-3"><label class="form-label" for="role-name">Tên vai trò *</label><input class="form-control @error('name') is-invalid @enderror" id="role-name" name="name" maxlength="100" value="{{ old('name', $role->name) }}" placeholder="Ví dụ: warehouse_manager" @readonly($systemRole) required><div class="form-text">{{ $systemRole ? 'Tên role hệ thống không thể thay đổi.' : 'Không dùng tên role hệ thống: '.implode(', ', \App\Models\Role::RESERVED_NAMES).'.' }}</div>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div><label class="form-label" for="role-description">Mô tả</label><textarea class="form-control @error('description') is-invalid @enderror" id="role-description" name="description" rows="4" maxlength="255" placeholder="Mô tả trách nhiệm của vai trò...">{{ old('description', $role->description) }}</textarea>@error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
    </section>

    @include('admin.roles.partials.permission-matrix', [
      'permissionGroups' => $permissionGroups,
      'selectedPermissionIds' => $selectedPermissionIds,
    ])

    <div class="d-flex justify-content-end gap-2 mt-4">
      <a class="btn btn-outline-secondary" href="{{ route('admin.roles.index') }}">Hủy</a>
      <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>{{ $editing ? 'Lưu vai trò và quyền' : 'Tạo vai trò và gán quyền' }}</button>
    </div>
  </form>
@endsection

@section('scripts')
  <script src="{{ asset('assets/admin/js/role-permission-matrix.js') }}"></script>
@endsection
