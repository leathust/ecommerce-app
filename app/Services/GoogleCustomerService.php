<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleCustomerService
{
    /**
     * Resolve a storefront customer from a Google account, provisioning one on first login.
     *
     * Accounts are matched strictly by email. Administrators are never resolved here so they
     * cannot reach the customer storefront through Google.
     */
    public function findOrCreate(SocialiteUser $googleUser): ?User
    {
        $email = $this->normalizeEmail($googleUser->getEmail());

        if ($email === null) {
            return null;
        }

        return DB::transaction(function () use ($googleUser, $email): ?User {
            $user = User::query()->where('email', $email)->lockForUpdate()->first();

            if ($user !== null) {
                return $user->isAdmin() ? null : $user;
            }

            $name = trim((string) $googleUser->getName());

            if ($name === '') {
                $name = $email;
            }

            $user = User::create([
                'name' => $name,
                'username' => $this->generateUsername($email),
                'email' => $email,
                'first_name' => $this->rawAttribute($googleUser, 'given_name'),
                'last_name' => $this->rawAttribute($googleUser, 'family_name'),
                'password' => null,
                'status' => 'active',
            ]);

            // Google verifies the address, so the account starts verified.
            $user->email_verified_at = now();
            $user->save();

            return $user;
        });
    }

    private function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function rawAttribute(SocialiteUser $user, string $key): ?string
    {
        $raw = $user->getRaw();

        if (! is_array($raw) || ! filled($raw[$key] ?? null)) {
            return null;
        }

        return mb_substr(trim((string) $raw[$key]), 0, 100);
    }

    private function generateUsername(string $email): string
    {
        $base = Str::ascii(Str::before($email, '@'));
        $base = (string) preg_replace('/[^A-Za-z0-9._-]+/', '_', $base);
        $base = trim($base, '._-');

        if ($base === '') {
            $base = 'user';
        }

        $base = Str::limit($base, 80, '');
        $candidate = $base;
        $suffix = 1;

        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }
}
