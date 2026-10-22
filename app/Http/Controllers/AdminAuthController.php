<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLoginForm(): View
    {
        return view('pages.login.login');
    }

    /**
     * @throws ValidationException
     */
    public function login(AdminLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->recordFailedAttempt();

            $this->throwAuthenticationException();
        }

        $user = $request->user();

        if (! $user || ! $user->isActive() || ! $user->isAdmin()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->recordFailedAttempt();

            $this->throwAuthenticationException();
        }

        $request->clearRateLimit();
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(url('/admin'));
    }

    /**
     * Always return a generic error to avoid revealing account state or role.
     *
     * @throws ValidationException
     */
    private function throwAuthenticationException(): never
    {
        throw ValidationException::withMessages([
            'email' => 'Thông tin đăng nhập không chính xác hoặc tài khoản không có quyền quản trị.',
        ]);
    }
}
