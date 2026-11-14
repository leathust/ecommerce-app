<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Services\CartIdentityService;
use App\Services\CheckoutService;
use App\Services\PaymentAccessTokenService;
use App\Support\CheckoutPageData;
use App\Support\StorefrontCartContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CheckoutController extends Controller
{
    public const IDEMPOTENCY_SESSION_KEY = 'checkout.idempotency_key';

    public const IDEMPOTENCY_CONSUMED_KEY = 'checkout.idempotency_consumed';

    public function index(Request $request, StorefrontCartContext $context, CheckoutPageData $page): Response|RedirectResponse
    {
        abort_if($this->isBlockedActor($request), Response::HTTP_FORBIDDEN);

        $cart = $context->cart(false);
        if (! $cart || ! $cart->items()->exists()) {
            return redirect()->route('cart')->withErrors(['cart' => __('frontend.checkout.cart_empty')]);
        }

        return response()
            ->view('frontend.checkout.index', [
                'checkout' => $page->build($cart, $request->user(), $this->idempotencyKey($request)),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(
        CheckoutRequest $request,
        CartIdentityService $identity,
        CheckoutService $checkout,
        PaymentAccessTokenService $access,
    ): JsonResponse|RedirectResponse {
        abort_if($this->isBlockedActor($request), Response::HTTP_FORBIDDEN);

        $sessionKey = $request->session()->get(self::IDEMPOTENCY_SESSION_KEY);
        $submittedKey = (string) $request->input('idempotency_key', '');
        if (! is_string($sessionKey) || $sessionKey === '' || ! hash_equals($sessionKey, $submittedKey)) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('frontend.checkout.session_expired'),
            ]);
        }

        $cart = $identity->resolve($request, false);
        if (! $cart) {
            return $this->missing($request);
        }

        $result = $checkout->checkout($cart, $request->user(), $request->validated());
        $attempt = $result['payment_attempt'];
        $redirectUrl = $attempt
            ? route('checkout.payment', ['paymentAttempt' => $attempt->public_id])
            : route('cart');
        $message = __('frontend.checkout.created');

        $payload = [
            'message' => $message,
            'created' => $result['created'],
            'order_code' => $result['order']->order_code,
            'payment_status' => $result['order']->payment_status,
            'payment_attempt' => $attempt?->public_id,
            'redirect_url' => $redirectUrl,
        ];

        if ($result['created']) {
            // Keep the key valid for duplicate submits, but the next GET /checkout must issue a new one.
            $request->session()->put(self::IDEMPOTENCY_CONSUMED_KEY, true);
        }

        if ($request->expectsJson()) {
            $response = response()->json($payload, $result['created'] ? 201 : 200);
        } else {
            $response = redirect()->to($redirectUrl)->with('cart_success', $message);
        }

        if ($attempt !== null && is_string($result['payment_access_token']) && $result['payment_access_token'] !== '') {
            $response->withCookie($access->cookie($result['payment_access_token'], $attempt));
        }

        return $response;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->session()->get(self::IDEMPOTENCY_SESSION_KEY);
        $consumed = (bool) $request->session()->get(self::IDEMPOTENCY_CONSUMED_KEY, false);

        if ($consumed || ! is_string($key) || preg_match('/^[a-f0-9]{64}$/i', $key) !== 1) {
            $key = bin2hex(random_bytes(32));
            $request->session()->put(self::IDEMPOTENCY_SESSION_KEY, $key);
            $request->session()->forget(self::IDEMPOTENCY_CONSUMED_KEY);
        }

        return $key;
    }

    private function isBlockedActor(Request $request): bool
    {
        $user = $request->user();

        return $user !== null && (! $user->isActive() || ! $user->isCustomer());
    }

    private function missing(CheckoutRequest $request): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => __('frontend.checkout.cart_empty'), 'errors' => ['cart' => [__('frontend.checkout.cart_empty')]]], 422)
            : redirect()->route('cart')->withErrors(['cart' => __('frontend.checkout.cart_empty')]);
    }
}
