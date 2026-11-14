<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\CheckoutService;
use App\Services\PaymentAccessTokenService;
use App\Support\CheckoutPaymentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutPaymentController extends Controller
{
    private const NO_STORE_HEADERS = [
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        'Pragma' => 'no-cache',
        'Vary' => 'Cookie',
        'X-Robots-Tag' => 'noindex, nofollow',
    ];

    public function __construct(
        private readonly PaymentAccessTokenService $access,
        private readonly CheckoutService $checkout,
        private readonly CheckoutPaymentPresenter $presenter,
    ) {
    }

    public function show(Request $request, PaymentAttempt $paymentAttempt): Response
    {
        $this->authorizeAccess($request, $paymentAttempt);
        $paymentAttempt = $this->expireWhenDue($paymentAttempt);

        return response()
            ->view('frontend.checkout.payment', [
                'payment' => $this->presenter->page($paymentAttempt),
            ])
            ->withHeaders(self::NO_STORE_HEADERS);
    }

    public function status(Request $request, PaymentAttempt $paymentAttempt): JsonResponse
    {
        $this->authorizeAccess($request, $paymentAttempt);
        $paymentAttempt = $this->expireWhenDue($paymentAttempt);

        return response()
            ->json($this->presenter->status($paymentAttempt))
            ->withHeaders(self::NO_STORE_HEADERS);
    }

    private function authorizeAccess(Request $request, PaymentAttempt $attempt): void
    {
        $attempt->loadMissing('order');

        abort_unless($attempt->order !== null && $this->access->authorize($request, $attempt), Response::HTTP_NOT_FOUND);
    }

    private function expireWhenDue(PaymentAttempt $attempt): PaymentAttempt
    {
        if (! $this->presenter->shouldExpire($attempt)) {
            return $attempt;
        }

        $this->checkout->expire($attempt);

        return PaymentAttempt::query()->with('order')->findOrFail($attempt->getKey());
    }
}
