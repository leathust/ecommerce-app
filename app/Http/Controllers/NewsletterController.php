<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNewsletterSubscriptionRequest;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class NewsletterController extends Controller
{
    public function store(StoreNewsletterSubscriptionRequest $request): JsonResponse|RedirectResponse
    {
        NewsletterSubscriber::query()->firstOrCreate(
            ['email' => $request->validated('email')],
            [
                'locale' => app()->getLocale(),
                'source' => 'home',
                'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
                'consented_at' => now(),
            ],
        );

        $message = __('frontend.home.newsletter_success');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect(route('home').'#newsletter')->with('newsletter_success', $message);
    }
}
