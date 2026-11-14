<?php

namespace App\Support;

use App\Models\Cart;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\SystemSettingService;

class CheckoutPageData
{
    public function __construct(
        private readonly CartPageData $cartPage,
        private readonly CheckoutService $checkout,
        private readonly SystemSettingService $settings,
    ) {
    }

    /** @return array<string, mixed> */
    public function build(?Cart $cart, ?User $customer, string $idempotencyKey): array
    {
        $cartPage = $this->cartPage->build($cart, app()->getLocale());
        $totalCents = (int) ($cartPage['estimated_total_cents'] ?? 0);
        $itemsAvailable = collect($cartPage['items'])->every(
            static fn (array $item): bool => (bool) $item['available'],
        );
        $codEnabled = (bool) $this->settings->get('payment.cod_enabled');
        $sepayStatus = $this->checkout->sepayAvailability($totalCents);
        $minimumMet = (bool) $cartPage['minimum_order_met'];
        $canSubmit = $cartPage['has_items']
            && $itemsAvailable
            && $minimumMet
            && ($codEnabled || $sepayStatus === 'available');

        $customerName = trim((string) ($customer?->name ?? ''));
        if ($customerName === '' && $customer) {
            $customerName = trim(($customer->last_name ?? '').' '.($customer->first_name ?? ''));
        }

        return [
            'cart' => $cartPage,
            'idempotency_key' => $idempotencyKey,
            'prefill' => [
                'recipient_name' => $customerName,
                'recipient_phone' => (string) ($customer?->phone ?? ''),
            ],
            'cod_enabled' => $codEnabled,
            'sepay_status' => $sepayStatus,
            'sepay_enabled' => $sepayStatus === 'available',
            'sepay_timeout_minutes' => (int) config('sepay.payment_timeout_minutes', 15),
            'default_method' => $codEnabled ? 'cod' : ($sepayStatus === 'available' ? 'sepay' : 'cod'),
            'can_submit' => $canSubmit,
            'total_cents' => $totalCents,
        ];
    }
}
