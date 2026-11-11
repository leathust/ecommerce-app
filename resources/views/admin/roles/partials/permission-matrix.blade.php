<section class="m-card" aria-labelledby="permission-matrix-title">
  <header class="m-card__header align-items-start">
    <div><h2 class="m-card__title" id="permission-matrix-title">Ma trận permission</h2><p class="m-card__subtitle">Tìm kiếm hoặc mở từng module để chọn quyền. Các nhóm được thu gọn mặc định để dễ theo dõi.</p></div>
    <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" id="permission-select-all"><label class="form-check-label fw-semibold" for="permission-select-all">Chọn tất cả</label></div>
  </header>

  @error('permission_ids')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
  @error('permission_ids.*')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

  <div class="row g-3 align-items-center mb-3">
    <div class="col-12 col-lg-8">
      <label class="visually-hidden" for="permission-search">Tìm permission</label>
      <div class="d-flex gap-2">
        <div class="position-relative flex-grow-1">
          <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted pe-none" aria-hidden="true"></i>
          <input class="form-control ps-5" id="permission-search" type="search" placeholder="Tìm theo tên hoặc mô tả permission..." autocomplete="off">
        </div>
        <button class="btn btn-outline-secondary flex-shrink-0" id="permission-search-clear" type="button" hidden>Xóa</button>
      </div>
    </div>
    <div class="col-12 col-lg-4 text-lg-end"><span class="badge text-bg-primary fs-6"><strong id="selected-permission-count">0</strong>/{{ $permissionGroups->sum(fn($group) => $group['permissions']->count()) }} đã chọn</span></div>
  </div>

  <div class="accordion" id="permission-matrix">
    @foreach($permissionGroups as $moduleKey => $group)
      @php($selectedInModule = $group['permissions']->whereIn('id', $selectedPermissionIds)->count())
      <div class="accordion-item permission-module" data-permission-module="{{ $moduleKey }}">
        <div class="accordion-header d-flex align-items-center" id="permission-heading-{{ $moduleKey }}">
          <button class="accordion-button collapsed flex-grow-1" type="button" data-bs-toggle="collapse" data-bs-target="#permission-collapse-{{ $moduleKey }}" aria-expanded="false" aria-controls="permission-collapse-{{ $moduleKey }}">
            <span class="d-flex flex-wrap align-items-center justify-content-between gap-2 w-100 pe-3"><span><strong>{{ $group['label'] }}</strong><small class="d-block text-muted fw-normal">{{ $group['description'] }}</small></span><span class="badge text-bg-light border"><span class="module-selected-count">{{ $selectedInModule }}</span>/{{ $group['permissions']->count() }}</span></span>
          </button>
          <div class="form-check form-switch px-3 mb-0 flex-shrink-0"><input class="form-check-input module-toggle" type="checkbox" id="module-{{ $moduleKey }}"><label class="form-check-label text-nowrap" for="module-{{ $moduleKey }}">Cả module</label></div>
        </div>
        <div class="accordion-collapse collapse" id="permission-collapse-{{ $moduleKey }}" aria-labelledby="permission-heading-{{ $moduleKey }}">
          <div class="accordion-body bg-body-tertiary">
            <div class="row g-2 permission-options">
              @foreach($group['permissions'] as $permission)
                @php($sensitivePermission = in_array($permission->name, ['roles.manage', 'permissions.manage', 'users.delete'], true))
                <div class="col-12 col-md-6 permission-option-wrap" data-permission-search="{{ \Illuminate\Support\Str::lower($permission->name.' '.$permission->description) }}">
                  <label class="border rounded-2 p-2 d-flex gap-2 h-100 permission-option bg-body {{ $sensitivePermission ? 'border-warning-subtle' : '' }}" for="permission-{{ $permission->id }}">
                    <input class="form-check-input permission-checkbox mt-1" type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" id="permission-{{ $permission->id }}" @checked($selectedPermissionIds->contains($permission->id))>
                    <span><code class="text-break">{{ $permission->name }}</code>@if($sensitivePermission)<span class="badge text-bg-warning ms-1">Nhạy cảm</span>@endif<small class="d-block text-muted mt-1">{{ $permission->description ?: 'Không có mô tả.' }}</small></span>
                  </label>
                </div>
              @endforeach
            </div>
            <p class="permission-module-empty text-muted text-center py-3 mb-0" hidden>Không có permission phù hợp trong module này.</p>
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <p class="text-center text-muted py-4 mb-0" id="permission-search-empty" hidden>Không tìm thấy permission phù hợp.</p>
  <div class="d-flex justify-content-end mt-3"><span class="small text-muted"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Permission catalog do code/seeder quản lý.</span></div>
</section>
