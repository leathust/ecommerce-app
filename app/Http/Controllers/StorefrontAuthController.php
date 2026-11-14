<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorefrontLoginRequest;
use App\Services\CartIdentityService;
use App\Support\SafeStorefrontRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontAuthController extends Controller
{
    public function showLoginForm(Request $request, SafeStorefrontRedirect $redirect): View|RedirectResponse
    {
        if ($request->user()?->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($request->user()?->isActive() && $request->user()?->isCustomer()) {
            return redirect()->route('home');
        }

        return view('frontend.auth.login', [
            'returnTo' => $redirect->resolve($request, $request->query('return_to')),
        ]);
    }

    /** @throws ValidationException */
    public function login(
        StorefrontLoginRequest $request,
        SafeStorefrontRedirect $redirect,
        CartIdentityService $cartIdentity,
    ): RedirectResponse {
        $request->ensureIsNotRateLimited();

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->recordFailedAttempt();
            $this->throwAuthenticationException();
        }

        $user = $request->user();

        if (! $user || ! $user->isActive() || ! $user->isCustomer()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->recordFailedAttempt();
            $this->throwAuthenticationException();
        }

        $request->clearRateLimit();
        $request->session()->regenerate();
        $cartIdentity->mergeGuestIntoCustomer($request, $user);

        $intended = $request->session()->pull('url.intended');
        $destination = $redirect->resolve($request, $request->validated('return_to'))
            ?? $redirect->resolve($request, $intended)
            ?? route('home');

        return redirect()->to($destination);
    }

    public function logout(Request $request, CartIdentityService $cartIdentity): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $cartIdentity->forgetGuestCookie();

        return redirect()->route('home');
    }

    /** @throws ValidationException */
    private function throwAuthenticationException(): never
    {
        throw ValidationException::withMessages([
            'email' => __('frontend.auth.failed'),
        ]);
    }
}
