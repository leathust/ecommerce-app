<?php

namespace App\Http\Controllers;

use App\Models\PaymentAttempt;
use App\Services\PaymentAccessTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pusher\Pusher;

class PaymentBroadcastAuthController extends Controller
{
    public function __construct(private readonly PaymentAccessTokenService $access)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $channel = (string) $request->input('channel_name', '');
        $socketId = (string) $request->input('socket_id', '');

        if (! preg_match('/^private-payment\.([0-9a-f-]{36})$/i', $channel, $matches)
            || ! Str::isUuid($matches[1])
            || ! preg_match('/^\d+\.\d+$/', $socketId)) {
            return response()->json(['message' => 'Invalid broadcast authorization request.'], 403);
        }

        $attempt = PaymentAttempt::query()
            ->with('order')
            ->where('public_id', $matches[1])
            ->first();

        if ($attempt === null || $attempt->order === null || ! $this->access->authorize($request, $attempt)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $connection = config('broadcasting.connections.pusher');
        if (! is_array($connection)
            || ! filled($connection['key'] ?? null)
            || ! filled($connection['secret'] ?? null)
            || ! filled($connection['app_id'] ?? null)) {
            return response()->json(['message' => 'Realtime payment updates are unavailable.'], 503);
        }

        $pusher = new Pusher(
            (string) $connection['key'],
            (string) $connection['secret'],
            (string) $connection['app_id'],
            (array) ($connection['options'] ?? []),
        );

        $payload = json_decode($pusher->authorizeChannel($channel, $socketId), true);

        return is_array($payload)
            ? response()->json($payload)
            : response()->json(['message' => 'Realtime payment authorization failed.'], 503);
    }
}
