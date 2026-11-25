<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly OrderInventoryService $inventory,
        private readonly PaymentAccessTokenService $paymentAccess,
        private readonly SePayReadinessService $sepayReadiness,
    ) {}

    /** @param array<string,mixed> $data
     *  @return array{order:Order,payment_attempt:?PaymentAttempt,created:bool,payment_access_token:?string}
     */
    public function checkout(Cart $cart, ?User $customer, array $data): array
    {
        return DB::transaction(function () use ($cart, $customer, $data): array {
            $existing = Order::query()->where('checkout_idempotency_key', $data['idempotency_key'])->first();
            if ($existing) {
                $this->ensureExistingOwner($existing, $customer);
                return ['order' => $existing, 'payment_attempt' => $existing->paymentAttempts()->latest('id')->first(), 'created' => false, 'payment_access_token' => null];
            }

            $lockedCart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            if ((int) ($lockedCart->user_id ?? 0) !== (int) ($customer?->id ?? 0)) {
                throw ValidationException::withMessages(['cart' => __('frontend.checkout.cart_invalid')]);
            }
            $items = $lockedCart->items()->orderBy('product_id')->lockForUpdate()->get();
            if ($items->isEmpty()) throw ValidationException::withMessages(['cart' => __('frontend.checkout.cart_empty')]);

            $products = Product::query()->with('category')->whereIn('id', $items->pluck('product_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $subtotalCents = 0; $snapshots = [];
            foreach ($items as $item) {
                $product = $products->get($item->product_id);
                if (! $product || ! $product->status || ! $product->category?->status || $product->quantity < $item->quantity) {
                    throw ValidationException::withMessages(['cart' => __('frontend.checkout.stock_changed')]);
                }
                $unitCents = (int) round((float) $product->price * 100);
                $lineCents = $unitCents * (int) $item->quantity; $subtotalCents += $lineCents;
                $snapshots[] = ['product_id'=>$product->id,'product_name'=>$product->name_vi,'sku'=>$product->sku,
                    'quantity'=>$item->quantity,'price'=>$unitCents / 100,'total_price'=>$lineCents / 100];
            }

            $minimum = $this->cents($this->settings->get('commerce.minimum_order_amount'));
            if ($subtotalCents < $minimum) throw ValidationException::withMessages(['cart' => __('frontend.checkout.minimum_not_met')]);
            $shipping = $this->cents($this->settings->get('commerce.default_shipping_fee'));
            $freeAt = $this->cents($this->settings->get('commerce.free_shipping_threshold'));
            if ($freeAt > 0 && $subtotalCents >= $freeAt) $shipping = 0;
            $totalCents = $subtotalCents + $shipping;
            $method = $data['payment_method'];
            if ($method === 'cod' && ! $this->settings->get('payment.cod_enabled')) throw ValidationException::withMessages(['payment_method'=>__('frontend.checkout.payment_disabled')]);
            if ($method === 'sepay') $this->ensureSePayReady($totalCents);

            $recipientEmail = $customer?->email ?? ($data['recipient_email'] ?? null);
            $recipientEmail = $recipientEmail !== null
                ? mb_strtolower(trim((string) $recipientEmail))
                : null;
            $locale = in_array(app()->getLocale(), ['vi', 'en'], true)
                ? app()->getLocale()
                : 'vi';

            $order = Order::create([
                'order_code'=>$this->newCode('ORD'),'checkout_idempotency_key'=>$data['idempotency_key'],'user_id'=>$customer?->id,
                'status'=>'pending','payment_method'=>$method === 'sepay' ? 'bank_transfer' : 'cod','payment_status'=>'unpaid',
                'subtotal'=>$subtotalCents/100,'shipping_fee'=>$shipping/100,'total_amount'=>$totalCents/100,
                'recipient_name'=>$data['recipient_name'],'recipient_phone'=>$data['recipient_phone'],
                'recipient_email'=>$recipientEmail, 'locale'=>$locale,
                'shipping_address'=>$data['shipping_address'],'note'=>$data['note'] ?? null,
            ]);
            $order->items()->createMany($snapshots);
            $attempt = null;
            $paymentAccessToken = null;
            if ($method === 'sepay') {
                $env = config('sepay.environment'); $account = config("sepay.environments.$env");
                if (! $customer) {
                    $paymentAccessToken = $this->newPaymentAccessToken();
                }
                $attempt = $order->paymentAttempts()->create([
                    'public_id'=>(string) Str::uuid(),'environment'=>$env,'provider'=>'sepay','payment_code'=>$this->newCode('DH'),
                    'idempotency_key'=>$data['idempotency_key'],'access_token_hash'=>$paymentAccessToken ? $this->paymentAccess->hash($paymentAccessToken) : null,
                    'expected_amount'=>intdiv($totalCents,100),'currency'=>'VND','status'=>'pending',
                    'bank_code'=>$account['bank_code'],'bank_account_number'=>$account['account_number'],'bank_account_name'=>$account['account_name'],
                    'expires_at'=>now()->addMinutes((int) config('sepay.payment_timeout_minutes')),'metadata'=>['cart_id'=>$lockedCart->id],
                ]);
                $this->inventory->deduct($order, now()); $order->save();
            }
            $lockedCart->items()->delete();
            return ['order'=>$order->fresh('items'),'payment_attempt'=>$attempt,'created'=>true,'payment_access_token'=>$paymentAccessToken];
        }, 3);
    }

    public function expire(PaymentAttempt $paymentAttempt): bool
    {
        return DB::transaction(function () use ($paymentAttempt): bool {
            $attempt = PaymentAttempt::query()->lockForUpdate()->findOrFail($paymentAttempt->id);
            if ($attempt->status !== 'pending' || $attempt->expires_at->isFuture()) return false;
            $order = Order::query()->lockForUpdate()->findOrFail($attempt->order_id);
            if ($order->payment_status === 'paid') return false;
            $this->inventory->restore($order, now());
            $order->forceFill(['status'=>'cancelled','cancel_reason'=>'SePay payment expired.','cancelled_at'=>now()])->save();
            $attempt->forceFill(['status'=>'expired','cancelled_at'=>now()])->save();
            return true;
        }, 3);
    }

    private function ensureExistingOwner(Order $order, ?User $customer): void
    { if ((int)($order->user_id ?? 0)!==(int)($customer?->id ?? 0)) throw ValidationException::withMessages(['idempotency_key'=>__('frontend.checkout.request_invalid')]); }

    /** @return 'available'|'disabled'|'unavailable' */
    public function sepayAvailability(int $totalCents): string
    {
        if (! $this->settings->get('payment.bank_transfer_enabled')) {
            return 'disabled';
        }

        if ($totalCents % 100 !== 0) {
            return 'unavailable';
        }

        return $this->sepayReadiness->checkoutConfiguration()['status'];
    }

    private function ensureSePayReady(int $totalCents): void
    {
        $availability = $this->sepayAvailability($totalCents);

        if ($availability === 'available') {
            return;
        }

        throw ValidationException::withMessages([
            'payment_method' => __($availability === 'unavailable'
                ? 'frontend.checkout.payment_unavailable'
                : 'frontend.checkout.payment_disabled'),
        ]);
    }

    private function newPaymentAccessToken(): string
    {
        do {
            $token = $this->paymentAccess->newToken();
        } while (PaymentAttempt::query()->where('access_token_hash', $this->paymentAccess->hash($token))->exists());

        return $token;
    }

    private function cents(mixed $value): int { return (int) round((float)$value*100); }
    private function newCode(string $prefix): string { do { $code=$prefix.Str::upper(bin2hex(random_bytes(6))); } while (Order::where('order_code',$code)->exists() || PaymentAttempt::where('payment_code',$code)->exists()); return $code; }
}
