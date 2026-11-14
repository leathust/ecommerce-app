<?php

namespace App\Http\Controllers;

use App\Services\CartIdentityService;
use App\Services\GoogleCustomerService;
use App\Services\GoogleOAuthService;
use App\Support\SafeStorefrontRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(
        Request $request,
        SafeStorefrontRedirect $redirect,
        GoogleOAuthService $google,
    ): RedirectResponse {
        abort_unless($google->enabled(), 404);

        $google->applyToConfig();

        $returnTo = $redirect->resolve($request, $request->query('return_to'));
        $request->session()->put('google.return_to', $returnTo);

        return Socialite::driver('google')->redirect();
    }

    public function callback(
        Request $request,
        GoogleCustomerService $customers,
        CartIdentityService $cartIdentity,
        SafeStorefrontRedirect $redirect,
        GoogleOAuthService $google,
    ): RedirectResponse {
        abort_unless($google->enabled(), 404);

        $google->applyToConfig();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $exception) {
            report($exception);
            $this->logoutIfAuthenticated($request);

            return redirect()->route('login')->withErrors([
                'email' => __('frontend.auth.google_failed'),
            ]);
        }

        $user = $customers->findOrCreate($googleUser);

        if ($user === null || ! $user->isActive() || ! $user->isCustomer()) {
            $this->logoutIfAuthenticated($request);

            return redirect()->route('login')->withErrors([
                'email' => $user === null
                    ? __('frontend.auth.google_failed')
                    : __('frontend.auth.inactive'),
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        $cartIdentity->mergeGuestIntoCustomer($request, $user);

        $destination = $redirect->resolve($request, $request->session()->pull('google.return_to'))
            ?? route('home');

        return redirect()->to($destination);
    }

    private function logoutIfAuthenticated(Request $request): void
    {
        if (! Auth::check()) {
            return;
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
