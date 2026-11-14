<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class PaymentAccessTokenService
{
    public const COOKIE_NAME = 'sepay_payment_access';

    public const COOKIE_PATH = '/checkout/payments';

    private const MAX_TTL_MINUTES = 60 * 24;

    public function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    public function cookie(string $rawToken, PaymentAttempt $attempt): Cookie
    {
        $expiresAt = $attempt->expires_at ?? now();
        $remainingMinutes = (int) ceil(max(0, now()->diffInSeconds($expiresAt, false)) / 60);
        $minutes = max(1, min(self::MAX_TTL_MINUTES, $remainingMinutes + (int) config('sepay.payment_access_grace_minutes', 30)));

        return cookie(
            self::COOKIE_NAME,
            $rawToken,
            $minutes,
            self::COOKIE_PATH,
            config('session.domain'),
            (bool) config('session.secure', false) || request()->isSecure(),
            true,
            false,
            (string) config('session.same_site', 'lax'),
        );
    }

    public function authorize(Request $request, PaymentAttempt $attempt): bool
    {
        $user = $request->user();

        if ($user instanceof User) {
            return $user->isActive()
                && $user->isCustomer()
                && $attempt->order !== null
                && (int) $attempt->order->user_id === (int) $user->getKey();
        }

        $rawToken = $request->cookie(self::COOKIE_NAME);
        $storedHash = $attempt->access_token_hash;

        return is_string($rawToken)
            && is_string($storedHash)
            && $storedHash !== ''
            && hash_equals($storedHash, $this->hash($rawToken));
    }
}
