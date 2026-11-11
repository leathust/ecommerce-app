<!DOCTYPE html>
<html lang="{{ $invoiceSettings['locale'] }}">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Hóa đơn {{ $order->order_code }} | {{ $invoiceSettings['site_name'] }}</title>
  <style>
    :root { color-scheme: light; --ink:#172033; --muted:#64748b; --line:#dbe2ea; --soft:#f6f8fb; --brand:#315fc4; }
    * { box-sizing: border-box; }
    body { margin:0; background:#eef1f5; color:var(--ink); font:14px/1.5 Arial, Helvetica, sans-serif; }
    .invoice-actions { max-width:900px; margin:22px auto 12px; display:flex; justify-content:flex-end; gap:10px; }
    .btn { border:1px solid var(--line); border-radius:8px; padding:10px 16px; background:#fff; color:var(--ink); text-decoration:none; cursor:pointer; font:inherit; font-weight:600; }
    .btn-primary { border-color:var(--brand); background:var(--brand); color:#fff; }
    .invoice { width:min(900px, calc(100% - 28px)); min-height:1120px; margin:0 auto 30px; padding:48px; background:#fff; box-shadow:0 8px 30px rgba(15,23,42,.08); }
    .invoice-header { display:flex; justify-content:space-between; gap:30px; padding-bottom:24px; border-bottom:2px solid var(--ink); }
    .brand { font-size:25px; font-weight:800; letter-spacing:-.02em; }
    .brand-logo { display:block; max-width:190px; max-height:64px; margin-bottom:8px; object-fit:contain; }
    .brand-subtitle, .muted { color:var(--muted); }
    .invoice-title { text-align:right; }
    .invoice-title h1 { margin:0 0 6px; font-size:28px; text-transform:uppercase; letter-spacing:.04em; }
    .invoice-title strong { font-size:16px; }
    .meta-grid { display:grid; grid-template-columns:1fr 1fr; gap:30px; padding:26px 0; }
    .section-title { margin:0 0 12px; font-size:13px; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); }
    .info-list { margin:0; }
    .info-row { display:grid; grid-template-columns:135px 1fr; gap:12px; margin:7px 0; }
    .info-row dt { color:var(--muted); }
    .info-row dd { margin:0; font-weight:600; white-space:pre-wrap; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:11px 10px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
    th { background:var(--soft); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
    .number { text-align:right; white-space:nowrap; }
    .product-name { font-weight:700; }
    .product-note { margin-top:2px; color:var(--muted); font-size:12px; }
    .summary { width:360px; max-width:100%; margin:22px 0 0 auto; }
    .summary-row { display:flex; justify-content:space-between; gap:20px; padding:7px 0; }
    .summary-total { margin-top:5px; padding-top:12px; border-top:2px solid var(--ink); font-size:18px; font-weight:800; }
    .invoice-note { margin-top:34px; padding:16px 18px; border-radius:8px; background:var(--soft); }
    .invoice-footer { margin-top:50px; padding-top:18px; border-top:1px solid var(--line); display:flex; justify-content:space-between; gap:20px; color:var(--muted); font-size:12px; }
    @page { size:A4; margin:12mm; }
    @media print {
      body { background:#fff; print-color-adjust:exact; -webkit-print-color-adjust:exact; }
      .no-print { display:none !important; }
      .invoice { width:100%; min-height:auto; margin:0; padding:0; box-shadow:none; }
      a { color:inherit; text-decoration:none; }
    }
    @media (max-width:640px) {
      .invoice { padding:24px; }
      .invoice-header, .meta-grid, .invoice-footer { display:block; }
      .invoice-title { margin-top:24px; text-align:left; }
      .meta-grid > section + section { margin-top:24px; }
      .table-wrap { overflow-x:auto; }
    }
  </style>
</head>
<body>
  <div class="invoice-actions no-print"><a class="btn" href="{{ route('admin.orders.show', $order) }}">Quay lại đơn hàng</a><button class="btn btn-primary" type="button" onclick="window.print()">In hóa đơn</button></div>
  <main class="invoice">
    <header class="invoice-header">
      <div>@if($invoiceSettings['logo_url'])<img class="brand-logo" src="{{ $invoiceSettings['logo_url'] }}" alt="{{ $invoiceSettings['site_name'] }}">@endif<div class="brand">{{ $invoiceSettings['legal_name'] }}</div><div class="brand-subtitle">Hóa đơn bán hàng</div></div>
      <div class="invoice-title"><h1>Hóa đơn</h1><strong>{{ $order->order_code }}</strong><div class="muted">Ngày tạo: {{ $order->created_at?->format('d/m/Y H:i') }}</div></div>
    </header>

    <div class="meta-grid">
      <section><h2 class="section-title">Thông tin người bán</h2><dl class="info-list">
        <div class="info-row"><dt>Đơn vị</dt><dd>{{ $invoiceSettings['legal_name'] }}</dd></div>
        @if($invoiceSettings['tax_code'])<div class="info-row"><dt>Mã số thuế</dt><dd>{{ $invoiceSettings['tax_code'] }}</dd></div>@endif
        @if($invoiceSettings['address'])<div class="info-row"><dt>Địa chỉ</dt><dd>{{ $invoiceSettings['address'] }}</dd></div>@endif
        @if($invoiceSettings['phone'])<div class="info-row"><dt>Điện thoại</dt><dd>{{ $invoiceSettings['phone'] }}</dd></div>@endif
        @if($invoiceSettings['email'])<div class="info-row"><dt>Email</dt><dd>{{ $invoiceSettings['email'] }}</dd></div>@endif
      </dl></section>
      <section><h2 class="section-title">Thông tin người nhận</h2><dl class="info-list">
        <div class="info-row"><dt>Họ tên</dt><dd>{{ $order->recipient_name }}</dd></div>
        <div class="info-row"><dt>Điện thoại</dt><dd>{{ $order->recipient_phone }}</dd></div>
        <div class="info-row"><dt>Địa chỉ</dt><dd>{{ $order->shipping_address }}</dd></div>
        @if($order->user)<div class="info-row"><dt>Email tài khoản</dt><dd>{{ $order->user->email }}</dd></div>@endif
      </dl></section>
    </div>

    <div class="meta-grid">
      <section><h2 class="section-title">Thông tin đơn hàng</h2><dl class="info-list">
        <div class="info-row"><dt>Trạng thái</dt><dd>{{ $statuses[$order->status] ?? $order->status }}</dd></div>
        <div class="info-row"><dt>Thanh toán</dt><dd>{{ $paymentStatuses[$order->payment_status] ?? $order->payment_status }}</dd></div>
        <div class="info-row"><dt>Phương thức</dt><dd>{{ $paymentMethods[$order->payment_method] ?? $order->payment_method }}</dd></div>
        <div class="info-row"><dt>Loại khách</dt><dd>{{ $order->user_id ? 'Khách có tài khoản' : 'Khách vãng lai' }}</dd></div>
      </dl></section>
    </div>

    <div class="table-wrap"><table>
      <thead><tr><th style="width:42px">STT</th><th>Sản phẩm</th><th>SKU</th><th class="number">Đơn giá</th><th class="number">SL</th><th class="number">Thành tiền</th></tr></thead>
      <tbody>@forelse($order->items as $item)<tr><td>{{ $loop->iteration }}</td><td><div class="product-name">{{ $item->product_name }}</div>@if(!$item->product_id)<div class="product-note">Sản phẩm gốc không còn tồn tại</div>@endif</td><td>{{ $item->sku ?: '—' }}</td><td class="number">{{ $moneyFormatter->format($item->price) }}</td><td class="number">{{ $item->quantity }}</td><td class="number"><strong>{{ $moneyFormatter->format($item->total_price) }}</strong></td></tr>@empty<tr><td colspan="6" style="text-align:center;color:var(--muted)">Không có dòng sản phẩm</td></tr>@endforelse</tbody>
    </table></div>

    <div class="summary">
      <div class="summary-row"><span>Tạm tính</span><strong>{{ $moneyFormatter->format($order->subtotal) }}</strong></div>
      <div class="summary-row"><span>Phí giao hàng</span><strong>{{ $moneyFormatter->format($order->shipping_fee) }}</strong></div>
      <div class="summary-row summary-total"><span>Tổng cộng</span><span>{{ $moneyFormatter->format($order->total_amount) }}</span></div>
    </div>

    @if($order->note)<div class="invoice-note"><strong>Ghi chú:</strong><div style="white-space:pre-wrap">{{ $order->note }}</div></div>@endif
    <footer class="invoice-footer"><span style="white-space:pre-line">{{ $invoiceSettings['footer'] }}</span><span>In lúc {{ now()->format('d/m/Y H:i') }}</span></footer>
  </main>
</body>
</html>
