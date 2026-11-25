<?php

namespace App\Providers;

use App\Http\Controllers\PaymentBroadcastAuthController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Broadcast::routes();

        // Keep guest payment authorization below the HttpOnly payment-cookie path.
        Route::post('/checkout/payments/broadcast-auth', PaymentBroadcastAuthController::class)
            ->middleware(['web', 'throttle:payment-status'])
            ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

        require base_path('routes/channels.php');
    }
}
