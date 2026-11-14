<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SePayWebhookController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $path = config('sepay.webhook_log_path');
        File::ensureDirectoryExists(dirname($path));

        $payload = $request->isJson() ? $request->json()->all() : $request->all();
        $entry = [
            'id' => (string) Str::uuid(),
            'received_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'payload' => $payload,
        ];

        $json = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        $written = file_put_contents($path, $json, FILE_APPEND | LOCK_EX);

        if ($written === false) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể ghi log SePay.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đã nhận webhook SePay.',
            'request_id' => $entry['id'],
            'received_at' => $entry['received_at'],
        ]);
    }

    public function index(): JsonResponse
    {
        $path = config('sepay.webhook_log_path');

        if (! File::exists($path)) {
            return response()->json([
                'file' => basename($path),
                'count' => 0,
                'entries' => [],
            ]);
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $entries = array_values(array_filter(array_map(
            static fn (string $line): ?array => json_decode($line, true),
            array_reverse(array_slice($lines, -100)),
        )));

        return response()->json([
            'file' => basename($path),
            'count' => count($entries),
            'showing_latest' => 100,
            'entries' => $entries,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
