<?php

namespace App\Support;

use App\Services\SystemSettingService;

class MoneyFormatter
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function format(int|float|string|null $amount): string
    {
        $currency = $this->settings->get('commerce.currency');
        $symbol = $this->settings->get('commerce.currency_symbol');
        $decimals = $currency === 'VND' ? 0 : 2;

        return number_format((float) ($amount ?? 0), $decimals, ',', '.').' '.$symbol;
    }
}
