<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateStorefrontProfileRequest;
use App\Http\Requests\UpdateStorefrontPasswordRequest;
use App\Models\User;
use App\Services\StorefrontProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontProfileController extends Controller
{
    public function show(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('frontend.account', [
            'account' => [
                'display_name' => $user->display_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'username' => $user->username,
                'phone' => $user->phone,
                'status' => $user->status,
                'registered_at' => $user->created_at?->toIso8601String(),
            ],
            'accountLinks' => [
                'cart' => route('cart'),
                'wishlist' => route('wishlist.index'),
            ],
        ]);
    }

    public function updateProfile(
        UpdateStorefrontProfileRequest $request,
        StorefrontProfileService $profiles,
    ): JsonResponse|RedirectResponse
    {
        /** @var User $customer */
        $customer = $profiles->updateProfile($request->user(), $request->validated());
        $message = __('frontend.profile.updated');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'profile' => $this->publicProfile($customer),
            ]);
        }

        return redirect()->route('account')->with('profile_success', $message);
    }

    public function updatePassword(
        UpdateStorefrontPasswordRequest $request,
        StorefrontProfileService $profiles,
    ): JsonResponse|RedirectResponse
    {
        $profiles->updatePassword($request->user(), $request->validated('password'));
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $message = __('frontend.profile.password_updated');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->route('account')->with('password_success', $message);
    }

    /** @return array{display_name: string, first_name: ?string, last_name: ?string, phone: ?string} */
    private function publicProfile(User $customer): array
    {
        return [
            'display_name' => $customer->display_name,
            'first_name' => $customer->first_name,
            'last_name' => $customer->last_name,
            'phone' => $customer->phone,
        ];
    }
}
