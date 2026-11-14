<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Cookie\CookieJar;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartIdentityService
{
    public const COOKIE_NAME = 'storefront_cart_token';

    public const COOKIE_MINUTES = 60 * 24 * 30;

    public const MAX_LINE_QUANTITY = 99;

    public function __construct(private readonly CookieJar $cookies)
    {
    }

    public function resolve(Request $request, bool $create = false): ?Cart
    {
        $user = $request->user();

        if ($user instanceof User) {
            if (! $user->isActive() || ! $user->isCustomer()) {
                return null;
            }

            return $this->resolveCustomer($user, $create);
        }

        return $this->resolveGuest($request, $create);
    }

    public function mergeGuestIntoCustomer(Request $request, User $customer): ?Cart
    {
        if (! $customer->isActive() || ! $customer->isCustomer()) {
            return null;
        }

        $rawToken = $this->validRawToken($request->cookie(self::COOKIE_NAME));

        if ($rawToken === null) {
            return $this->resolveCustomer($customer, false);
        }

        $tokenHash = $this->hashToken($rawToken);

        $cart = DB::transaction(function () use ($customer, $tokenHash): ?Cart {
            $guestCart = Cart::query()
                ->whereNull('user_id')
                ->where('session_token', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $guestCart) {
                return $this->resolveCustomer($customer, false);
            }

            $customerCart = Cart::query()->forUser($customer)->lockForUpdate()->first()
                ?? $this->resolveCustomer($customer, true);

            if (! $customerCart) {
                return null;
            }

            $guestItems = $guestCart->items()->lockForUpdate()->get();
            $customerItems = $customerCart->items()->lockForUpdate()->get()->keyBy('product_id');
            $stocks = Product::query()
                ->whereIn('id', $guestItems->pluck('product_id'))
                ->lockForUpdate()
                ->pluck('quantity', 'id');

            foreach ($guestItems as $guestItem) {
                $customerItem = $customerItems->get($guestItem->product_id);
                $combined = (int) $guestItem->quantity + (int) ($customerItem?->quantity ?? 0);
                $stock = (int) ($stocks[$guestItem->product_id] ?? 0);
                $limit = $stock > 0 ? min($stock, self::MAX_LINE_QUANTITY) : self::MAX_LINE_QUANTITY;
                $quantity = max(1, min($combined, $limit));

                $customerCart->items()->updateOrCreate(
                    ['product_id' => $guestItem->product_id],
                    ['quantity' => $quantity],
                );
            }

            $guestCart->delete();

            return $customerCart->fresh('items');
        }, 3);

        $this->forgetGuestCookie();

        return $cart;
    }

    public function forgetGuestCookie(): void
    {
        $this->cookies->queue($this->cookies->forget(
            self::COOKIE_NAME,
            (string) config('session.path', '/'),
            config('session.domain'),
        ));
    }

    public function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    private function resolveCustomer(User $customer, bool $create): ?Cart
    {
        $cart = Cart::query()->forUser($customer)->first();

        if ($cart || ! $create) {
            return $cart;
        }

        try {
            return Cart::query()->create([
                'user_id' => $customer->getKey(),
                'session_token' => null,
            ]);
        } catch (QueryException $exception) {
            return Cart::query()->forUser($customer)->first() ?? throw $exception;
        }
    }

    private function resolveGuest(Request $request, bool $create): ?Cart
    {
        $rawToken = $this->validRawToken($request->cookie(self::COOKIE_NAME));

        if ($rawToken !== null) {
            $cart = Cart::query()->forSessionHash($this->hashToken($rawToken))->first();

            if ($cart || ! $create) {
                return $cart;
            }
        } elseif (! $create) {
            return null;
        }

        $rawToken = $this->newRawToken();
        $cart = Cart::query()->create([
            'user_id' => null,
            'session_token' => $this->hashToken($rawToken),
        ]);
        $this->queueGuestCookie($request, $rawToken);

        return $cart;
    }

    private function queueGuestCookie(Request $request, string $rawToken): void
    {
        $secure = (bool) config('session.secure', false) || $request->isSecure();

        $this->cookies->queue($this->cookies->make(
            self::COOKIE_NAME,
            $rawToken,
            self::COOKIE_MINUTES,
            (string) config('session.path', '/'),
            config('session.domain'),
            $secure,
            true,
            false,
            (string) config('session.same_site', 'lax'),
        ));
    }

    private function newRawToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function validRawToken(mixed $token): ?string
    {
        return is_string($token) && preg_match('/^[A-Za-z0-9_-]{43}$/D', $token) === 1
            ? $token
            : null;
    }
}
