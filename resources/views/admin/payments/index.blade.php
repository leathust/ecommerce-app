@extends('admin.layout')

@section('content')
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div><h1 class="h3 mb-1">Đối soát thanh toán</h1><p class="text-muted mb-0">Theo dõi giao dịch SePay, webhook bị thiếu và các ngoại lệ cần xử lý.</p></div>
  </div>

  <section class="row g-3 mb-4" aria-label="Tổng quan giao dịch">
    @foreach(['unmatched' => ['Chưa khớp', 'warning'], 'manual_review' => ['Cần rà soát', 'danger'], 'matched' => ['Đã khớp', 'success'], 'ignored' => ['Đã bỏ qua', 'secondary']] as $status => [$label, $color])
      <div class="col-6 col-xl-3"><div class="m-card h-100"><div class="text-muted small">{{ $label }}</div><div class="fs-3 fw-bold text-{{ $color }} mt-1">{{ number_format((int) ($statusCounts[$status] ?? 0)) }}</div></div></div>
    @endforeach
  </section>
  <section class="row g-3 mb-4" aria-label="KPI đối soát">
    <div class="col-6 col-xl-3"><div class="m-card h-100"><div class="text-muted small">Webhook lag TB (24h)</div><div class="fs-3 fw-bold mt-1">{{ number_format((float) ($metrics['webhook_lag_seconds'] ?? 0), 1) }}s</div></div></div>
    <div class="col-6 col-xl-3"><div class="m-card h-100"><div class="text-muted small">Attempt pending quá hạn</div><div class="fs-3 fw-bold text-danger mt-1">{{ number_format((int) ($metrics['pending_expired_attempts'] ?? 0)) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="m-card h-100"><div class="text-muted small">Reconciliation thất bại</div><div class="fs-3 fw-bold text-danger mt-1">{{ number_format((int) ($metrics['reconciliation_failures'] ?? 0)) }}</div></div></div>
    <div class="col-6 col-xl-3"><div class="m-card h-100"><div class="text-muted small">Webhook lỗi</div><div class="fs-3 fw-bold text-warning mt-1">{{ number_format((int) ($metrics['failed_webhooks'] ?? 0)) }}</div></div></div>
  </section>

  <section class="m-card mb-4" aria-labelledby="payment-filter-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="payment-filter-title">Bộ lọc giao dịch</h2><p class="m-card__subtitle">Lọc theo environment, trạng thái, lý do hoặc mã giao dịch.</p></div></header>
    <form action="{{ route('admin.payments.reconciliation') }}" method="GET" class="row g-3 align-items-end">
      <div class="col-12 col-xl-4"><label class="form-label" for="payment-search">Tìm kiếm</label><input class="form-control" id="payment-search" type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" placeholder="External ID, mã đơn, nội dung..."></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="payment-environment">Environment</label><select class="form-select" id="payment-environment" name="environment"><option value="">Tất cả</option>@foreach($environments as $environment)<option value="{{ $environment }}" @selected(($filters['environment'] ?? '') === $environment)>{{ ucfirst($environment) }}</option>@endforeach</select></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="payment-match-status">Match status</label><select class="form-select" id="payment-match-status" name="match_status"><option value="">Tất cả</option>@foreach($matchStatuses as $status)<option value="{{ $status }}" @selected(($filters['match_status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
      <div class="col-12 col-md-4 col-xl-2"><label class="form-label" for="payment-reason">Reason</label><input class="form-control" id="payment-reason" type="text" name="match_reason" value="{{ $filters['match_reason'] ?? '' }}" maxlength="100"></div>
      <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="payment-date-from">Từ ngày</label><input class="form-control" id="payment-date-from" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
      <div class="col-12 col-md-6 col-xl-2"><label class="form-label" for="payment-date-to">Đến ngày</label><input class="form-control" id="payment-date-to" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
      <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter me-1" aria-hidden="true"></i>Áp dụng</button><a class="btn btn-outline-secondary" href="{{ route('admin.payments.reconciliation') }}">Xóa lọc</a></div>
    </form>
  </section>

  <section class="m-card mb-4" aria-labelledby="payment-list-title">
    <header class="m-card__header"><div><h2 class="m-card__title" id="payment-list-title">Giao dịch SePay</h2><p class="m-card__subtitle">{{ number_format($transactions->total()) }} giao dịch, mới nhất hiển thị trước.</p></div></header>
    @if($transactions->isEmpty())
      <div class="text-center py-5 text-muted"><i class="fa-solid fa-money-check-dollar fs-1 mb-3" aria-hidden="true"></i><p class="mb-0">Chưa có giao dịch phù hợp.</p></div>
    @else
      <div class="table-responsive"><table class="m-table table align-middle"><thead><tr><th>Thời gian</th><th>External ID</th><th>Environment</th><th>Số tiền</th><th>Nội dung</th><th>Match</th><th>Thao tác</th></tr></thead><tbody>
        @foreach($transactions as $transaction)
          @php($statusClass = match($transaction->match_status) {'matched' => 'text-bg-success', 'manual_review' => 'text-bg-danger', 'unmatched' => 'text-bg-warning', default => 'text-bg-secondary'})
          <tr><td><time datetime="{{ $transaction->transaction_at?->toIso8601String() }}">{{ $transaction->transaction_at?->format('d/m/Y H:i:s') }}</time></td><td><code>{{ $transaction->external_transaction_id }}</code>@if($transaction->order)<div class="small mt-1"><a href="{{ route('admin.orders.show', $transaction->order) }}">{{ $transaction->order->order_code }}</a></div>@endif</td><td><span class="badge text-bg-light border">{{ $transaction->environment }}</span></td><td class="text-nowrap"><strong>{{ number_format($transaction->amount) }} {{ $transaction->currency }}</strong><div class="small text-muted">{{ $transaction->direction }}</div></td><td class="text-break" style="max-width:280px">{{ $transaction->transfer_content }}</td><td><span class="badge {{ $statusClass }}">{{ $transaction->match_status }}</span><div class="small text-muted mt-1">{{ $transaction->match_reason }}</div></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('admin.payments.transactions.show', $transaction) }}">Chi tiết</a></td></tr>
        @endforeach
      </tbody></table></div>
      <div class="mt-4">{{ $transactions->links() }}</div>
    @endif
  </section>

  <section class="m-card" aria-labelledby="run-list-title"><header class="m-card__header"><div><h2 class="m-card__title" id="run-list-title">Reconciliation runs gần đây</h2><p class="m-card__subtitle">Checkpoint giúp resume run lỗi mà không xử lý trùng.</p></div></header><div class="table-responsive"><table class="m-table table align-middle"><thead><tr><th>Public ID</th><th>Environment</th><th>Trạng thái</th><th>Fetched</th><th>Matched</th><th>Review</th><th></th></tr></thead><tbody>@forelse($runs as $run)<tr><td><code>{{ $run->public_id }}</code></td><td>{{ $run->environment }}</td><td><span class="badge text-bg-light border">{{ $run->status }}</span></td><td>{{ $run->fetched_count }}</td><td>{{ $run->matched_count }}</td><td>{{ $run->review_count }}</td><td><a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.payments.reconciliation.runs.show', $run) }}">Xem run</a></td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-3">Chưa có reconciliation run.</td></tr>@endforelse</tbody></table></div></section>
@endsection
