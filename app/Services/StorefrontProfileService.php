<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class StorefrontProfileService
{
    /**
     * @param array{first_name?: ?string, last_name?: ?string, phone?: ?string} $attributes
     */
    public function updateProfile(User $customer, array $attributes): User
    {
        return DB::transaction(function () use ($customer, $attributes): User {
            $firstName = $attributes['first_name'] ?? null;
            $lastName = $attributes['last_name'] ?? null;
            $displayName = trim(implode(' ', array_filter([$firstName, $lastName])));

            $customer->first_name = $firstName;
            $customer->last_name = $lastName;
            $customer->phone = $attributes['phone'] ?? null;

            if ($displayName !== '') {
                $customer->name = $displayName;
            }

            $customer->save();

            return $customer->refresh();
        });
    }

    public function updatePassword(User $customer, string $password): User
    {
        return DB::transaction(function () use ($customer, $password): User {
            $customer->password = $password;
            $customer->save();

            return $customer->refresh();
        });
    }
}
