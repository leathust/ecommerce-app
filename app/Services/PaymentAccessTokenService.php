<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
            $this->cookiePath(),
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

        $storedHash = is_string($attempt->access_token_hash) ? trim($attempt->access_token_hash) : '';
        if ($storedHash === '') {
            return false;
        }

        $cookieValue = $request->cookie(self::COOKIE_NAME);
        if (! is_string($cookieValue) || trim($cookieValue) === '') {
            return false;
        }

        foreach ($this->tokenCandidates($cookieValue) as $candidate) {
            if (hash_equals($storedHash, $this->hash($candidate))) {
                return true;
            }
        }

        return false;
    }

    private function cookiePath(): string
    {
        $requestPath = parse_url((string) request()->getRequestUri(), PHP_URL_PATH);
        $requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
        $checkoutPosition = strpos($requestPath, '/checkout');

        if ($checkoutPosition !== false) {
            $basePath = rtrim(substr($requestPath, 0, $checkoutPosition), '/');
        } else {
            $configuredPath = parse_url((string) config('app.url', ''), PHP_URL_PATH);
            $basePath = is_string($configuredPath) ? rtrim($configuredPath, '/') : '';
        }

        return $basePath.self::COOKIE_PATH;
    }

    /** @return list<string> */
    private function tokenCandidates(string $cookieValue): array
    {
        $candidates = [trim($cookieValue)];

        // The web middleware decrypts cookies before the controller runs. This fallback
        // keeps authorization working for requests built outside that middleware
        // (tests, CLI probes, internal sub-requests) without accepting an unrelated value.
        try {
            $decrypted = Crypt::decryptString($cookieValue);
            $validated = CookieValuePrefix::validate(self::COOKIE_NAME, $decrypted, Crypt::getKey());

            if (is_string($validated) && $validated !== '') {
                $candidates[] = trim($validated);
            }
        } catch (DecryptException) {
            // Raw token or encrypted with another key: the direct comparison above decides.
        }

        return array_values(array_unique($candidates));
    }
}
