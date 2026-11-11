<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Role;
use App\Policies\AdminUserPolicy;
use App\Policies\RolePolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => AdminUserPolicy::class,
        Role::class => RolePolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return $user->hasPermission($ability) ? true : null;
        });
    }
}
