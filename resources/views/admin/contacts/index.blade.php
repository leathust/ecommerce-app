@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div>
      <h1 class="h3 mb-1">Danh sách liên hệ</h1>
      <p class="text-muted mb-0">Tiếp nhận và theo dõi yêu cầu hỗ trợ từ khách hàng.</p>
    </div>
    <span class="badge text-bg-primary">{{ $contacts->total() }} liên hệ</span>
  </div>

  @if (session('success'))
    <div class="alert alert-success" role="status">{{ session('success') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger" role="alert">
      @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
  @endif

  <section class="m-card mb-4" aria-labelledby="contact-filter-title">
    <header class="m-card__header">
      <div>
        <h2 class="m-card__title" id="contact-filter-title">Tìm kiếm và lọc</h2>
        <p class="m-card__subtitle">Lọc theo trạng thái, chủ đề hoặc thông tin người gửi.</p>
      </div>
    </header>
    <form action="{{ route('admin.contacts.index') }}" method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-lg-5">
        <label class="form-label" for="contact-search">Tìm kiếm</label>
        <input id="contact-search" class="form-control" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="Tên, email, điện thoại, mã đơn hàng...">
      </div>
      <div class="col-12 col-md-5 col-lg-3">
        <label class="form-label" for="contact-status">Trạng thái</label>
        <select id="contact-status" class="form-select" name="status">
          <option value="">Tất cả trạng thái</option>
          @foreach ($statuses as $value => $label)
            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-md-5 col-lg-3">
        <label class="form-label" for="contact-reason">Chủ đề</label>
        <select id="contact-reason" class="form-select" name="reason">
          <option value="">Tất cả chủ đề</option>
          @foreach ($reasons as $value => $label)
            <option value="{{ $value }}" @selected(($filters['reason'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12 col-lg-1 d-flex gap-2">
        <button class="btn btn-primary" type="submit" aria-label="Áp dụng bộ lọc"><i class="fa-solid fa-filter"></i></button>
        <a class="btn btn-outline-secondary" href="{{ route('admin.contacts.index') }}" aria-label="Xóa bộ lọc"><i class="fa-solid fa-rotate-left"></i></a>
      </div>
    </form>
  </section>

  <section class="m-card" aria-labelledby="contact-list-title">
    <header class="m-card__header">
      <div>
        <h2 class="m-card__title" id="contact-list-title">Yêu cầu hỗ trợ</h2>
        <p class="m-card__subtitle">Hiển thị mới nhất trước, 15 bản ghi mỗi trang.</p>
      </div>
    </header>

    @if ($contacts->isEmpty())
      <div class="text-center py-5">
        <i class="fa-regular fa-envelope-open fs-1 text-muted" aria-hidden="true"></i>
        <h3 class="h5 mt-3">Không tìm thấy liên hệ</h3>
        <p class="text-muted mb-0">Hãy thử thay đổi điều kiện tìm kiếm hoặc bộ lọc.</p>
      </div>
    @else
      <div class="table-responsive">
        <table class="m-table table align-middle">
          <thead>
            <tr>
              <th scope="col">#</th>
              <th scope="col">Người gửi</th>
              <th scope="col">Chủ đề</th>
              <th scope="col">Nội dung</th>
              <th scope="col">Thời gian</th>
              <th scope="col">Trạng thái</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($contacts as $contact)
              @php
                $statusClass = match ($contact->status) {
                    'new' => 'text-bg-primary',
                    'processing' => 'text-bg-warning',
                    'resolved' => 'text-bg-success',
                    default => 'text-bg-secondary',
                };
              @endphp
              <tr>
                <td>{{ $contact->id }}</td>
                <td>
                  <strong>{{ $contact->full_name }}</strong>
                  <div><a href="mailto:{{ $contact->email }}">{{ $contact->email }}</a></div>
                  @if ($contact->phone)<small class="text-muted">{{ $contact->phone }}</small>@endif
                </td>
                <td>
                  <span>{{ $reasons[$contact->reason] ?? $contact->reason }}</span>
                  @if ($contact->order_code)<div><small class="text-muted">Đơn: {{ $contact->order_code }}</small></div>@endif
                </td>
                <td style="min-width:260px;max-width:420px">
                  <details>
                    <summary>{{ Illuminate\Support\Str::limit($contact->message, 90) }}</summary>
                    <p class="mt-2 mb-0" style="white-space:pre-wrap">{{ $contact->message }}</p>
                  </details>
                </td>
                <td><time datetime="{{ $contact->created_at?->toIso8601String() }}">{{ $contact->created_at?->format('d/m/Y H:i') }}</time></td>
                <td style="min-width:170px">
                  @can('contacts.update')
                    <form action="{{ route('admin.contacts.status.update', $contact) }}" method="POST">
                      @csrf
                      @method('PATCH')
                      <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" aria-label="Trạng thái liên hệ #{{ $contact->id }}">
                        @foreach ($statuses as $value => $label)
                          <option value="{{ $value }}" @selected($contact->status === $value)>{{ $label }}</option>
                        @endforeach
                      </select>
                    </form>
                    @if (filter_var($contact->email, FILTER_VALIDATE_EMAIL))
                      <button type="button" class="btn btn-sm btn-outline-primary mt-2"
                        data-bs-toggle="modal" data-bs-target="#contact-reply-modal"
                        data-reply-action="{{ route('admin.contacts.reply', $contact) }}"
                        data-reply-email="{{ $contact->email }}"
                        data-reply-id="{{ $contact->id }}">
                        <i class="fa-solid fa-reply" aria-hidden="true"></i> Trả lời
                      </button>
                    @endif
                  @else
                    <span class="badge {{ $statusClass }}">{{ $statuses[$contact->status] ?? $contact->status }}</span>
                  @endcan
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
  </section>

@endsection

@push('modals')
  @can('contacts.update')
    <div class="modal fade" id="contact-reply-modal" tabindex="-1" aria-labelledby="contact-reply-title" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <form id="contact-reply-form" method="POST">
            @csrf
            <input type="hidden" name="reply_contact_id" id="reply-contact-id" value="{{ old('reply_contact_id') }}">
            <div class="modal-header">
              <h2 class="modal-title fs-5" id="contact-reply-title">Trả lời liên hệ</h2>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
              <p>Người nhận: <strong id="reply-recipient"></strong></p>
              <div class="mb-3">
                <label class="form-label" for="reply-subject">Tiêu đề</label>
                <input class="form-control" id="reply-subject" name="subject" maxlength="200" required value="{{ old('subject', 'Phản hồi yêu cầu liên hệ - '.config('app.name')) }}">
              </div>
              <div class="mb-3">
                <label class="form-label" for="reply-message">Nội dung trả lời</label>
                <textarea class="form-control" id="reply-message" name="reply_message" rows="8" maxlength="10000" required>{{ old('reply_message') }}</textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
              <button type="submit" id="reply-submit" class="btn btn-primary">Gửi email</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endcan
@endpush

@section('scripts')
  @can('contacts.update')
    <script>
      const replyModal = document.getElementById('contact-reply-modal');
      const replyForm = document.getElementById('contact-reply-form');
      const replyButton = document.getElementById('reply-submit');
      let previousContact = document.getElementById('reply-contact-id').value;
      replyModal.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        if (previousContact !== trigger.dataset.replyId) {
          document.getElementById('reply-message').value = '';
        }
        previousContact = trigger.dataset.replyId;
        replyForm.action = trigger.dataset.replyAction;
        document.getElementById('reply-contact-id').value = previousContact;
        document.getElementById('reply-recipient').textContent = trigger.dataset.replyEmail;
        replyButton.disabled = false;
        replyButton.textContent = 'Gửi email';
      });
      replyForm.addEventListener('submit', function () {
        replyButton.disabled = true;
        replyButton.textContent = 'Đang gửi...';
      });
      @if ($errors->has('subject') || $errors->has('reply_message'))
        const retryTrigger = Array.from(document.querySelectorAll('[data-reply-id]'))
          .find(button => button.dataset.replyId === previousContact);
        if (retryTrigger) new bootstrap.Modal(replyModal).show(retryTrigger);
      @endif
    </script>
  @endcan
@endsection
