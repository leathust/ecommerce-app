<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    /**
     * Allow users that have at least one of the requested permissions.
     *
     * Permissions may be separated with commas in route middleware arguments
     * or with pipes inside one argument, for example permission:orders.view|orders.update.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        $permissionNames = collect($permissions)
            ->flatMap(fn (string $permission) => explode('|', $permission))
            ->map(fn (string $permission) => trim($permission))
            ->filter()
            ->unique()
            ->values();

        abort_if($permissionNames->isEmpty(), Response::HTTP_FORBIDDEN);
        abort_unless($user->hasAnyPermission($permissionNames), Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
