<?php

namespace Tests\Feature;

use App\Models\PaymentAttempt;
use App\Services\PaymentAccessTokenService;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class PaymentAccessTokenServiceTest extends TestCase
{
    public function test_authorize_accepts_the_raw_token_cookie(): void
    {
        $service = app(PaymentAccessTokenService::class);
        $rawToken = $service->newToken();
        $attempt = new PaymentAttempt(['access_token_hash' => $service->hash($rawToken)]);

        $request = Request::create('/checkout/payments/example', 'GET', [], [
            PaymentAccessTokenService::COOKIE_NAME => $rawToken,
        ]);

        $this->assertTrue($service->authorize($request, $attempt));
    }

    public function test_authorize_accepts_an_encrypted_cookie_value_outside_the_cookie_middleware(): void
    {
        $service = app(PaymentAccessTokenService::class);
        $rawToken = $service->newToken();
        $attempt = new PaymentAttempt(['access_token_hash' => $service->hash($rawToken)]);
        $encrypted = Crypt::encryptString(
            CookieValuePrefix::create(PaymentAccessTokenService::COOKIE_NAME, Crypt::getKey()).$rawToken,
        );

        $request = Request::create('/checkout/payments/example', 'GET', [], [
            PaymentAccessTokenService::COOKIE_NAME => $encrypted,
        ]);

        $this->assertTrue($service->authorize($request, $attempt));
    }

    public function test_authorize_rejects_a_mismatched_missing_or_empty_token(): void
    {
        $service = app(PaymentAccessTokenService::class);
        $rawToken = $service->newToken();
        $attempt = new PaymentAttempt(['access_token_hash' => $service->hash($rawToken)]);

        $wrong = Request::create('/checkout/payments/example', 'GET', [], [
            PaymentAccessTokenService::COOKIE_NAME => $service->newToken(),
        ]);
        $missing = Request::create('/checkout/payments/example', 'GET');
        $emptyHashAttempt = new PaymentAttempt(['access_token_hash' => null]);

        $this->assertFalse($service->authorize($wrong, $attempt));
        $this->assertFalse($service->authorize($missing, $attempt));
        $this->assertFalse($service->authorize($missing, $emptyHashAttempt));
    }

    public function test_token_format_and_cookie_path_follow_the_checkout_base_path(): void
    {
        $service = app(PaymentAccessTokenService::class);
        $rawToken = $service->newToken();
        $attempt = new PaymentAttempt(['expires_at' => now()->addMinutes(15)]);

        $this->assertSame(43, strlen($rawToken));
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $rawToken);

        $this->app->instance('request', Request::create('/ecommerce-app/checkout', 'POST'));
        $subdirectoryCookie = $service->cookie($rawToken, $attempt);
        $this->assertSame('/ecommerce-app/checkout/payments', $subdirectoryCookie->getPath());

        $this->app->instance('request', Request::create('/checkout', 'POST'));
        $rootCookie = $service->cookie($rawToken, $attempt);
        $this->assertSame('/checkout/payments', $rootCookie->getPath());
    }
}
