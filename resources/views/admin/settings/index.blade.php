@extends('admin.layout')

@section('content')
  @php
    $canUpdate = auth()->user()->hasPermission('settings.update');
    $oldSettings = old('settings', []);
    $groupMeta = $groups[$selectedGroup];
    $settingIcon = static function (string $key, array $definition): string {
      return match (true) {
        str_contains($key, 'facebook') => 'fa-brands fa-facebook',
        str_contains($key, 'instagram') => 'fa-brands fa-instagram',
        str_contains($key, 'youtube') => 'fa-brands fa-youtube',
        str_contains($key, 'tiktok') => 'fa-brands fa-tiktok',
        str_contains($key, 'favicon') => 'fa-solid fa-star',
        str_contains($key, 'logo'), $definition['type'] === 'image' => 'fa-regular fa-image',
        str_contains($key, 'timezone'), str_contains($key, 'working_hours') => 'fa-regular fa-clock',
        str_contains($key, 'locale') => 'fa-solid fa-language',
        str_contains($key, 'email'), str_starts_with($key, 'mail.') => 'fa-regular fa-envelope',
        str_contains($key, 'phone') => 'fa-solid fa-phone',
        str_contains($key, 'address') => 'fa-solid fa-location-dot',
        str_contains($key, 'tax_code') => 'fa-solid fa-receipt',
        str_contains($key, 'invoice') => 'fa-solid fa-file-invoice',
        str_contains($key, 'shipping') => 'fa-solid fa-truck-fast',
        str_contains($key, 'stock') => 'fa-solid fa-boxes-stacked',
        str_contains($key, 'currency'), str_contains($key, 'amount') => 'fa-solid fa-coins',
        str_contains($key, 'bank') => 'fa-solid fa-building-columns',
        str_contains($key, 'card') => 'fa-regular fa-credit-card',
        str_contains($key, 'cod') => 'fa-solid fa-money-bill-wave',
        str_contains($key, 'indexing') => 'fa-solid fa-robot',
        str_contains($key, 'meta_title') => 'fa-solid fa-heading',
        str_contains($key, 'description') => 'fa-solid fa-align-left',
        str_contains($key, 'tagline') => 'fa-solid fa-quote-left',
        str_contains($key, 'name') => 'fa-solid fa-signature',
        $definition['type'] === 'bool' => 'fa-solid fa-toggle-on',
        default => 'fa-solid fa-sliders',
      };
    };
  @endphp

  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button>
    </div>
  @endif

  <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 mb-1">Cài đặt hệ thống</h1>
      <p class="text-muted mb-0">Quản lý thông tin vận hành theo registry; credential và secret vẫn được bảo vệ trong môi trường triển khai.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge text-bg-light border align-self-center"><i class="fa-solid fa-shield-halved me-1" aria-hidden="true"></i>Không lưu secret</span>
      @if(!$canUpdate)<span class="badge text-bg-warning align-self-center"><i class="fa-solid fa-eye me-1" aria-hidden="true"></i>Chỉ xem</span>@endif
    </div>
  </div>

  <section class="m-card mb-4" aria-labelledby="settings-navigation-title">
    <header class="m-card__header">
      <div><h2 class="m-card__title" id="settings-navigation-title">Nhóm cấu hình</h2><p class="m-card__subtitle">Mỗi lần lưu chỉ cập nhật một nhóm và tạo một sự kiện audit.</p></div>
    </header>
    <ul class="nav nav-tabs settings-tabs flex-nowrap overflow-auto" aria-label="Nhóm cài đặt">
      @foreach($groups as $groupKey => $group)
        <li class="nav-item">
          <a class="nav-link text-nowrap {{ $selectedGroup === $groupKey ? 'active' : '' }}" href="{{ route('admin.settings.index', ['group' => $groupKey]) }}" @if($selectedGroup === $groupKey) aria-current="page" @endif>
            <i class="{{ $group['icon'] }}" aria-hidden="true"></i>
            {{ $group['label'] }}
          </a>
        </li>
      @endforeach
    </ul>
  </section>

  <div data-settings-feedback aria-live="polite"></div>

  <form method="POST" action="{{ route('admin.settings.update', $selectedGroup) }}" enctype="multipart/form-data" data-settings-form novalidate>
    @csrf
    @method('PUT')

    <section class="m-card" aria-labelledby="settings-group-title">
      <header class="m-card__header">
        <div>
          <h2 class="m-card__title d-flex align-items-center gap-2" id="settings-group-title"><i class="{{ $groupMeta['icon'] }} text-primary" aria-hidden="true"></i>{{ $groupMeta['label'] }}</h2>
          <p class="m-card__subtitle">{{ $groupMeta['description'] }}</p>
        </div>
        <span class="badge text-bg-primary">{{ count($definitions) }} trường</span>
      </header>

      <div class="alert alert-danger" role="alert" data-settings-error-summary @if(!$errors->has('settings')) hidden @endif>
        @error('settings'){{ $message }}@enderror
      </div>

      <div class="row g-4">
        @foreach($definitions as $key => $definition)
          @php
            $fieldId = 'setting-'.str_replace('.', '-', $key);
            $value = array_key_exists($key, $oldSettings) ? $oldSettings[$key] : ($values[$key] ?? $definition['default']);
            $fieldErrors = array_merge(
              $errors->get('settings.'.$key),
              $errors->get('assets.'.$key),
              $errors->get('remove_assets.'.$key),
            );
            $isPlanned = $definition['runtime_status'] === 'planned_consumer';
            $isRuntimeLocked = $key === 'mail.contact_auto_reply_enabled';
          @endphp

          <div class="col-12 {{ in_array($definition['type'], ['textarea', 'image'], true) ? '' : 'col-xl-6' }}">
            <div class="border rounded-3 p-3 h-100 bg-white">
              <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
                <label class="form-label fw-semibold mb-0 setting-field__title" for="{{ $fieldId }}">
                  <span class="setting-field__icon" aria-hidden="true"><i class="{{ $settingIcon($key, $definition) }}"></i></span>
                  <span>{{ $definition['label'] }}</span>
                </label>
                <span class="badge {{ $isPlanned ? 'text-bg-warning' : 'text-bg-success' }}">{{ $isPlanned ? 'Chuẩn bị' : 'Đang áp dụng' }}</span>
              </div>
              <p class="small text-muted mb-3">{{ $definition['description'] }}</p>

              @if($definition['type'] === 'image')
                <div class="row g-3 align-items-start">
                  <div class="col-12 col-lg-4">
                    <div class="settings-image-preview" id="preview-{{ $fieldId }}">
                      <img class="img-thumbnail w-100 settings-image-preview__image" data-settings-preview-image data-original-src="{{ $assetUrls[$key] ?? '' }}" @if($assetUrls[$key] ?? null) src="{{ $assetUrls[$key] }}" @else hidden @endif alt="Xem trước {{ $definition['label'] }}">
                      <div class="border rounded-3 bg-light flex-column align-items-center justify-content-center text-muted text-center p-4 settings-image-preview__empty" data-settings-preview-empty @if($assetUrls[$key] ?? null) hidden @endif>
                        <i class="fa-regular fa-image fs-2 mb-2" aria-hidden="true"></i>
                        <span>Chưa có ảnh managed</span>
                      </div>
                    </div>
                  </div>
                  <div class="col-12 col-lg-8">
                    <label class="form-label" for="{{ $fieldId }}">Tải file mới</label>
                    <input class="form-control @if($fieldErrors) is-invalid @endif" id="{{ $fieldId }}" type="file" name="assets[{{ $key }}]" accept="{{ $definition['upload']['accept'] }}" data-settings-image-input data-preview-target="preview-{{ $fieldId }}" @disabled(!$canUpdate)>
                    <div class="form-text">Ảnh được xem trước ngay trên trình duyệt; file mới chỉ thay file cũ sau khi settings và audit lưu thành công.</div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2" data-settings-preview-actions hidden>
                      <span class="small text-success" data-settings-preview-status></span>
                      <button class="btn btn-sm btn-outline-secondary" type="button" data-settings-preview-clear><i class="fa-solid fa-rotate-left me-1" aria-hidden="true"></i>Hoàn tác ảnh chọn</button>
                    </div>
                    @if(filled($value))
                      <div class="small text-muted text-break mt-2">Path hiện tại: <code>{{ $value }}</code></div>
                      @if($canUpdate)
                        <div class="form-check mt-2">
                          <input class="form-check-input" type="checkbox" id="remove-{{ $fieldId }}" name="remove_assets[{{ $key }}]" value="1">
                          <label class="form-check-label text-danger" for="remove-{{ $fieldId }}">Xóa ảnh hiện tại</label>
                        </div>
                      @endif
                    @endif
                  </div>
                </div>
              @elseif($definition['type'] === 'bool')
                <input type="hidden" name="settings[{{ $key }}]" value="0" @disabled(!$canUpdate)>
                <div class="form-check form-switch pt-1">
                  <input class="form-check-input @if($fieldErrors) is-invalid @endif" type="checkbox" role="switch" id="{{ $fieldId }}" name="settings[{{ $key }}]" value="1" @checked((bool)$value) @disabled(!$canUpdate || $isRuntimeLocked)>
                  <label class="form-check-label" for="{{ $fieldId }}">{{ (bool)$value ? 'Đang bật' : 'Đang tắt' }}</label>
                </div>
              @elseif($definition['type'] === 'enum')
                <select class="form-select @if($fieldErrors) is-invalid @endif" id="{{ $fieldId }}" name="settings[{{ $key }}]" @disabled(!$canUpdate)>
                  @foreach($definition['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string)$value === (string)$optionValue)>{{ $optionLabel }}</option>
                  @endforeach
                </select>
              @elseif($definition['type'] === 'textarea')
                <textarea class="form-control @if($fieldErrors) is-invalid @endif" id="{{ $fieldId }}" name="settings[{{ $key }}]" rows="4" @disabled(!$canUpdate)>{{ $value }}</textarea>
              @else
                @php
                  $inputType = match($definition['type']) {
                    'email' => 'email',
                    'url' => 'url',
                    'int', 'decimal' => 'number',
                    default => 'text',
                  };
                @endphp
                <input class="form-control @if($fieldErrors) is-invalid @endif" id="{{ $fieldId }}" type="{{ $inputType }}" name="settings[{{ $key }}]" value="{{ $value }}" @if($definition['type'] === 'int') step="1" min="0" @elseif($definition['type'] === 'decimal') step="0.01" min="0" @endif @disabled(!$canUpdate)>
              @endif

              @foreach($fieldErrors as $message)<div class="invalid-feedback d-block">{{ $message }}</div>@endforeach
              @if($isPlanned)<div class="small text-warning-emphasis mt-2"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Cấu hình này chưa tác động runtime cho đến khi consumer tương ứng được triển khai.</div>@endif
            </div>
          </div>
        @endforeach
      </div>

      @if($canUpdate)
        <div class="d-flex justify-content-end mt-4">
          <button class="btn btn-primary" type="submit" data-settings-submit><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i><span data-settings-submit-label>Lưu {{ $groupMeta['label'] }}</span></button>
        </div>
      @endif
    </section>
  </form>
@endsection

@section('scripts')
<script>
  (() => {
    const previewUrls = new Map();
    const form = document.querySelector('[data-settings-form]');
    const feedback = document.querySelector('[data-settings-feedback]');
    const errorSummary = form?.querySelector('[data-settings-error-summary]');

    const restorePreview = (input) => {
      const preview = document.getElementById(input.dataset.previewTarget);
      if (!preview) return;

      const image = preview.querySelector('[data-settings-preview-image]');
      const empty = preview.querySelector('[data-settings-preview-empty]');
      const actions = input.parentElement.querySelector('[data-settings-preview-actions]');
      const previousUrl = previewUrls.get(input);
      if (previousUrl) URL.revokeObjectURL(previousUrl);
      previewUrls.delete(input);

      const originalSrc = image.dataset.originalSrc;
      if (originalSrc) {
        image.src = originalSrc;
        image.hidden = false;
        empty.hidden = true;
      } else {
        image.removeAttribute('src');
        image.hidden = true;
        empty.hidden = false;
      }
      actions.hidden = true;
    };

    document.querySelectorAll('[data-settings-image-input]').forEach((input) => {
      const preview = document.getElementById(input.dataset.previewTarget);
      const actions = input.parentElement.querySelector('[data-settings-preview-actions]');
      const status = actions.querySelector('[data-settings-preview-status]');
      const clear = actions.querySelector('[data-settings-preview-clear]');

      input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (!file) {
          restorePreview(input);
          return;
        }

        const previousUrl = previewUrls.get(input);
        if (previousUrl) URL.revokeObjectURL(previousUrl);
        const previewUrl = URL.createObjectURL(file);
        previewUrls.set(input, previewUrl);

        const image = preview.querySelector('[data-settings-preview-image]');
        image.src = previewUrl;
        image.hidden = false;
        preview.querySelector('[data-settings-preview-empty]').hidden = true;
        status.textContent = `Đang xem trước: ${file.name}`;
        actions.hidden = false;

        const removeCheckbox = document.getElementById(`remove-${input.id}`);
        if (removeCheckbox) removeCheckbox.checked = false;
      });

      clear.addEventListener('click', () => {
        input.value = '';
        restorePreview(input);
        input.focus();
      });
    });

    const clearValidationErrors = () => {
      form.querySelectorAll('.is-invalid').forEach((field) => field.classList.remove('is-invalid'));
      form.querySelectorAll('[data-settings-ajax-error]').forEach((error) => error.remove());
      errorSummary.hidden = true;
      errorSummary.textContent = '';
    };

    const fieldForError = (key) => {
      const match = key.match(/^(settings|assets|remove_assets)\.(.+)$/);
      if (!match) return null;
      const name = `${match[1]}[${match[2]}]`;
      return Array.from(form.elements).find((field) => field.name === name && field.type !== 'hidden')
        || Array.from(form.elements).find((field) => field.name === name)
        || null;
    };

    const showValidationErrors = (errors) => {
      let firstInvalid = null;
      const generalMessages = [];

      Object.entries(errors).forEach(([key, messages]) => {
        const field = fieldForError(key);
        if (!field) {
          generalMessages.push(...messages);
          return;
        }

        field.classList.add('is-invalid');
        const error = document.createElement('div');
        error.className = 'invalid-feedback d-block';
        error.dataset.settingsAjaxError = 'true';
        error.textContent = messages[0];
        field.insertAdjacentElement('afterend', error);
        firstInvalid ||= field;
      });

      if (!firstInvalid && generalMessages.length === 0) {
        generalMessages.push('Dữ liệu chưa hợp lệ. Vui lòng kiểm tra lại các trường cài đặt.');
      }

      if (generalMessages.length) {
        errorSummary.textContent = generalMessages.join(' ');
        errorSummary.hidden = false;
      }

      (firstInvalid || errorSummary).scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid?.focus({ preventScroll: true });
    };

    form?.addEventListener('input', (event) => {
      const field = event.target;
      if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) return;
      field.classList.remove('is-invalid');
      const error = field.nextElementSibling;
      if (error?.hasAttribute('data-settings-ajax-error')) error.remove();
    });

    const renderFeedback = (message, type) => {
      feedback.replaceChildren();
      const alert = document.createElement('div');
      alert.className = `alert alert-${type}`;
      alert.setAttribute('role', type === 'success' ? 'status' : 'alert');
      alert.textContent = message;
      feedback.append(alert);
      alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    const commitAssetPreviews = (assetUrls) => {
      form.querySelectorAll('[data-settings-image-input]').forEach((input) => {
        const key = input.name.match(/^assets\[(.+)]$/)?.[1];
        if (!key || !Object.hasOwn(assetUrls, key)) return;
        const preview = document.getElementById(input.dataset.previewTarget);
        const image = preview.querySelector('[data-settings-preview-image]');
        const empty = preview.querySelector('[data-settings-preview-empty]');
        const actions = input.parentElement.querySelector('[data-settings-preview-actions]');
        const previewUrl = previewUrls.get(input);
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrls.delete(input);
        input.value = '';
        image.dataset.originalSrc = assetUrls[key] || '';

        if (assetUrls[key]) {
          image.src = assetUrls[key];
          image.hidden = false;
          empty.hidden = true;
        } else {
          image.removeAttribute('src');
          image.hidden = true;
          empty.hidden = false;
        }
        actions.hidden = true;
      });
    };

    form?.addEventListener('submit', async (event) => {
      event.preventDefault();
      clearValidationErrors();
      feedback.replaceChildren();

      const submit = form.querySelector('[data-settings-submit]');
      const submitLabel = submit.querySelector('[data-settings-submit-label]');
      const originalLabel = submitLabel.textContent;
      submit.disabled = true;
      submitLabel.textContent = 'Đang lưu...';

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        });
        const payload = await response.json().catch(() => ({}));

        if (response.status === 422) {
          showValidationErrors(payload.errors || {});
          return;
        }
        if (!response.ok) throw new Error(payload.message || 'Không thể lưu cài đặt lúc này.');

        commitAssetPreviews(payload.asset_urls || {});
        form.querySelectorAll('[name^="remove_assets["]').forEach((checkbox) => { checkbox.checked = false; });
        renderFeedback(payload.message || 'Đã lưu cài đặt.', 'success');
      } catch (error) {
        renderFeedback(error.message || 'Không thể lưu cài đặt lúc này.', 'danger');
      } finally {
        submit.disabled = false;
        submitLabel.textContent = originalLabel;
      }
    });

    window.addEventListener('beforeunload', () => {
      previewUrls.forEach((url) => URL.revokeObjectURL(url));
    });
  })();
</script>
@endsection
