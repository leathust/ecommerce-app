<?php

namespace App\Http\Controllers;

use App\Http\Requests\SePayWebhookRequest;
use App\Services\SePayWebhookIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SePayWebhookController extends Controller
{
    public function store(Request $request, SePayWebhookIngestionService $ingestion): JsonResponse
    {
        $payload = $request->attributes->get('sepay.payload');
        if (! is_array($payload)) {
            return response()->json(['success' => false, 'error' => 'invalid_payload'], 400);
        }

        $validator = Validator::make($payload, SePayWebhookRequest::payloadRules());
        if ($validator->fails()) {
            return response()->json(['success' => false, 'error' => 'invalid_payload'], 400);
        }

        $ingestion->ingest($request, $validator->validated());

        return response()->json(['success' => true]);
    }
}
