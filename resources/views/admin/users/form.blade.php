@extends('admin.layout')

@section('content')
  @php
    $editing = $managedUser->exists;
    $isSelf = $editing && auth()->id() === $managedUser->id;
    $selectedRoles = collect(old('role_ids', $editing ? $managedUser->roles->pluck('id')->all() : []))->map(fn ($id) => (string) $id);
  @endphp

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
      <nav aria-label="Điều hướng"><ol class="breadcrumb mb-2"><li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">Tài khoản &amp; quyền</a></li><li class="breadcrumb-item active" aria-current="page">{{ $editing ? 'Cập nhật' : 'Tạo mới' }}</li></ol></nav>
      <h1 class="h3 mb-1">{{ $editing ? 'Cập nhật tài khoản quản trị' : 'Tạo tài khoản quản trị' }}</h1>
      <p class="text-muted mb-0">Thông tin đăng nhập, trạng thái bảo mật và vai trò quản trị.</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ $editing ? route('admin.users.show', $managedUser) : route('admin.users.index') }}"><i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>Quay lại</a>
  </div>

  <form method="POST" action="{{ $editing ? route('admin.users.update', $managedUser) : route('admin.users.store') }}" novalidate>
    @csrf
    @if($editing) @method('PUT') @endif

    <div class="row g-4">
      <div class="col-12 col-xl-7">
        <section class="m-card mb-4" aria-labelledby="account-profile-title">
          <header class="m-card__header"><div><h2 class="m-card__title" id="account-profile-title">Hồ sơ tài khoản</h2><p class="m-card__subtitle">Các trường có dấu * là bắt buộc.</p></div></header>
          <div class="row g-3">
            <div class="col-12"><label class="form-label" for="name">Tên hiển thị *</label><input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $managedUser->name) }}" maxlength="255" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="first_name">Tên</label><input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $managedUser->first_name) }}" maxlength="100">@error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="last_name">Họ</label><input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $managedUser->last_name) }}" maxlength="100">@error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="username">Username *</label><input class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username', $managedUser->username) }}" maxlength="100" autocomplete="username" required>@error('username')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="email">Email *</label><input class="form-control @error('email') is-invalid @enderror" id="email" type="email" name="email" value="{{ old('email', $managedUser->email) }}" maxlength="255" required>@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="phone">Điện thoại</label><input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $managedUser->phone) }}" maxlength="30">@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @if($editing)
              <div class="col-md-6"><label class="form-label" for="status">Trạng thái *</label><select class="form-select @error('status') is-invalid @enderror" id="status" name="status" @disabled($isSelf)>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', $managedUser->status) === $value)>{{ $label }}</option>@endforeach</select>@if($isSelf)<input type="hidden" name="status" value="{{ $managedUser->status }}"><div class="form-text">Không thể tự thay đổi trạng thái tài khoản.</div>@endif @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @endif
          </div>
        </section>

        <section class="m-card" aria-labelledby="account-password-title">
          <header class="m-card__header"><div><h2 class="m-card__title" id="account-password-title">Mật khẩu</h2><p class="m-card__subtitle">Ít nhất 12 ký tự, gồm chữ hoa, chữ thường, số và ký tự đặc biệt. {{ $editing ? 'Để trống nếu không đổi.' : '' }}</p></div></header>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="password">Mật khẩu {{ $editing ? '' : '*' }}</label><input class="form-control @error('password') is-invalid @enderror" id="password" type="password" name="password" autocomplete="new-password" {{ $editing ? '' : 'required' }}>@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label" for="password_confirmation">Xác nhận mật khẩu {{ $editing ? '' : '*' }}</label><input class="form-control" id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" {{ $editing ? '' : 'required' }}></div>
            <div class="col-12"><div class="form-check"><input class="form-check-input" id="must_change_password" type="checkbox" name="must_change_password" value="1" @checked(old('must_change_password', $managedUser->must_change_password))><label class="form-check-label" for="must_change_password">Yêu cầu đổi mật khẩu ở lần đăng nhập tiếp theo</label></div></div>
          </div>
        </section>
      </div>

      <div class="col-12 col-xl-5">
        <section class="m-card position-sticky" style="top: 1rem" aria-labelledby="account-role-title">
          <header class="m-card__header"><div><h2 class="m-card__title" id="account-role-title">Vai trò quản trị *</h2><p class="m-card__subtitle">Bắt buộc chọn ít nhất một role. Hệ thống chỉ hiển thị role bạn được phép gán.</p></div></header>
          @error('role_ids')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
          @error('role_ids.*')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
          <div class="d-flex flex-column gap-2">
            @foreach($roles as $role)
              <label class="border rounded p-3 d-flex align-items-start gap-3 {{ $isSelf ? 'text-muted' : '' }}">
                <input class="form-check-input mt-1" type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked($selectedRoles->contains((string) $role->id)) @disabled($isSelf)>
                <span><strong>{{ $role->name }}</strong>@if($role->name === \App\Models\Role::SUPER_ADMIN)<span class="badge text-bg-dark ms-1">Đặc quyền</span>@endif<div class="small text-muted">{{ $role->description ?: 'Không có mô tả' }} · {{ $role->permissions->count() }} quyền</div></span>
              </label>
            @endforeach
          </div>
          @if($isSelf)
            @foreach($managedUser->roles as $role)<input type="hidden" name="role_ids[]" value="{{ $role->id }}">@endforeach
            <div class="alert alert-info mt-3 mb-0">Bạn có thể sửa hồ sơ và mật khẩu, nhưng không thể tự đổi vai trò hoặc trạng thái.</div>
          @endif
          <div class="d-grid mt-4"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>{{ $editing ? 'Lưu thay đổi' : 'Tạo tài khoản' }}</button></div>
        </section>
      </div>
    </div>
  </form>
@endsection
