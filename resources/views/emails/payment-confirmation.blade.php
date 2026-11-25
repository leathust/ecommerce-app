<!doctype html>
<html lang="{{ $locale }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <title>{{ strtr($template['subject'], [':order_code' => $order->order_code]) }}</title>
  <style>
    body { margin: 0; padding: 0; width: 100%; background: #f1f5f9; color: #172033; font-family: Arial, Helvetica, sans-serif; }
    table { border-collapse: collapse; }
    a { color: #4f46e5; }
    .email-shell { width: 100%; background: #f1f5f9; padding: 32px 12px; }
    .email-card { width: 100%; max-width: 680px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden; }
    .email-header { background: #312e81; padding: 34px 36px; color: #ffffff; }
    .eyebrow { color: #c7d2fe; font-size: 12px; font-weight: 700; letter-spacing: 1.4px; text-transform: uppercase; }
    .email-title { margin: 10px 0 0; color: #ffffff; font-size: 28px; line-height: 1.2; }
    .email-body { padding: 34px 36px; }
    .intro { margin: 0 0 24px; color: #475569; font-size: 16px; line-height: 1.7; }
    .status-row { margin: 0 0 26px; }
    .status-badge { display: inline-block; padding: 8px 12px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; letter-spacing: .4px; }
    .summary { width: 100%; margin: 0 0 28px; border: 1px solid #e2e8f0; border-radius: 12px; }
    .summary td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
    .summary tr:last-child td { border-bottom: 0; }
    .summary-label { width: 38%; color: #64748b; }
    .summary-value { color: #172033; font-weight: 700; text-align: right; }
    .section-title { margin: 0 0 12px; color: #172033; font-size: 17px; }
    .items { width: 100%; margin: 0 0 20px; }
    .items th { padding: 10px 0; border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 11px; letter-spacing: .8px; text-align: left; text-transform: uppercase; }
    .items td { padding: 14px 0; border-bottom: 1px solid #f1f5f9; color: #334155; font-size: 14px; vertical-align: top; }
    .items .qty { width: 14%; color: #64748b; text-align: center; }
    .items .price { width: 24%; color: #172033; font-weight: 700; text-align: right; }
    .total-row td { padding: 18px 0 0; border-bottom: 0; color: #172033; font-size: 18px; font-weight: 700; }
    .footer { padding: 24px 36px 30px; background: #f8fafc; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 13px; line-height: 1.6; }
    @media screen and (max-width: 600px) {
      .email-shell { padding: 12px 0; }
      .email-card { border-right: 0; border-left: 0; border-radius: 0; }
      .email-header, .email-body { padding: 26px 22px; }
      .footer { padding: 22px; }
      .email-title { font-size: 24px; }
      .summary-label { width: 45%; }
    }
  </style>
</head>
<body>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="email-shell">
    <tr>
      <td align="center">
        <table role="presentation" cellpadding="0" cellspacing="0" class="email-card">
          <tr>
            <td class="email-header">
              <div class="eyebrow">Ecommerce App · SePay</div>
              <h1 class="email-title">{{ trans('frontend.mail.payment_confirmation.heading', [], $locale) }}</h1>
            </td>
          </tr>
          <tr>
            <td class="email-body">
              <p class="intro">{{ strtr($template['intro'], [':name' => $order->recipient_name, ':order_code' => $order->order_code]) }}</p>
              <div class="status-row"><span class="status-badge">✓ {{ trans('frontend.mail.payment_confirmation.heading', [], $locale) }}</span></div>

              <table role="presentation" cellpadding="0" cellspacing="0" class="summary">
                <tr>
                  <td class="summary-label">{{ trans('frontend.mail.payment_confirmation.order_code', [], $locale) }}</td>
                  <td class="summary-value">{{ $order->order_code }}</td>
                </tr>
                <tr>
                  <td class="summary-label">{{ trans('frontend.mail.payment_confirmation.paid_at', [], $locale) }}</td>
                  <td class="summary-value">{{ optional($order->paid_at)->format('d/m/Y H:i') }}</td>
                </tr>
                <tr>
                  <td class="summary-label">{{ trans('frontend.mail.payment_confirmation.recipient', [], $locale) }}</td>
                  <td class="summary-value">{{ $order->recipient_name }}</td>
                </tr>
              </table>

              <h2 class="section-title">{{ trans('frontend.mail.payment_confirmation.items', [], $locale) }}</h2>
              <table role="presentation" cellpadding="0" cellspacing="0" class="items">
                <thead>
                  <tr>
                    <th>{{ trans('frontend.mail.payment_confirmation.items', [], $locale) }}</th>
                    <th class="qty">Qty</th>
                    <th class="price">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach ($order->items as $item)
                    <tr>
                      <td>{{ $item->product_name }}@if($item->sku)<br><span style="color:#94a3b8;font-size:12px">{{ $item->sku }}</span>@endif</td>
                      <td class="qty">{{ $item->quantity }}</td>
                      <td class="price">{{ number_format((float) $item->total_price, 0, ',', '.') }} VND</td>
                    </tr>
                  @endforeach
                  <tr class="total-row">
                    <td colspan="2">{{ trans('frontend.mail.payment_confirmation.total', [], $locale) }}</td>
                    <td class="price">{{ number_format((float) $order->total_amount, 0, ',', '.') }} VND</td>
                  </tr>
                </tbody>
              </table>
            </td>
          </tr>
          <tr>
            <td class="footer">{{ $template['footer'] }}</td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
