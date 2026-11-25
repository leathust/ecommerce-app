<?php

use App\Models\PaymentAttempt;
use App\Services\PaymentAccessTokenService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('payment.{publicId}', function ($user, string $publicId): bool {
    $attempt = PaymentAttempt::query()
        ->where('public_id', $publicId)
        ->first();

    return $attempt !== null
        && $attempt->order()->exists()
        && app(PaymentAccessTokenService::class)->authorize(request(), $attempt);
});
