<?php

namespace App\Support;

use App\Models\PaymentAttempt;
use RuntimeException;

class SePayQrCodeUrl
{
    public function build(PaymentAttempt $attempt): string
    {
        $bank = trim((string) $attempt->bank_code);
        $account = trim((string) $attempt->bank_account_number);
        $code = trim((string) $attempt->payment_code);
        $amount = (int) $attempt->expected_amount;

        if ($bank === '' || $account === '' || $code === '' || $amount <= 0) {
            throw new RuntimeException('Payment attempt is missing QR data.');
        }

        $params = [
            'acc' => $account,
            'bank' => $bank,
            'amount' => $amount,
            'des' => $code,
        ];

        $template = trim((string) config('sepay.qr_template', 'compact'));
        if ($template !== '') {
            $params['template'] = $template;
        }

        $base = rtrim((string) config('sepay.qr_base_url', 'https://vietqr.app/img'), '?');

        return $base.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }
}
