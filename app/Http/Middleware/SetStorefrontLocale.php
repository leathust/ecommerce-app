<?php

namespace App\Http\Middleware;

use App\Support\StorefrontLocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function __construct(private readonly StorefrontLocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($this->resolver->resolve($request));

        try {
            return $next($request);
        } finally {
            // Prevent the storefront locale leaking into admin or another request
            // when the application is served by a long-running worker.
            app()->setLocale($previousLocale);
        }
    }
}
