<?php

namespace App\Services;

use App\Jobs\ProcessSePayWebhookJob;
use App\Models\SePayWebhookReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SePayWebhookIngestionService
{
    /** @return array{status: string, receipt: SePayWebhookReceipt} */
    public function ingest(Request $request, array $payload): array
    {
        $environment = (string) $request->attributes->get('sepay.environment');
        $rawBody = (string) $request->attributes->get('sepay.raw_body');
        $externalId = (string) $payload['id'];
        $payloadHash = hash('sha256', $rawBody);
        $headers = $this->auditHeaders($request);

        $receipt = SePayWebhookReceipt::query()
            ->where('environment', $environment)
            ->where('external_event_id', $externalId)
            ->first();

        if ($receipt) {
            if (in_array($receipt->status, ['received', 'failed'], true)) {
                $this->dispatch($receipt);
                return ['status' => 'redispatched_duplicate', 'receipt' => $receipt->fresh()];
            }

            return ['status' => 'duplicate', 'receipt' => $receipt];
        }

        try {
            $receipt = DB::transaction(function () use ($environment, $externalId, $payloadHash, $rawBody, $headers): SePayWebhookReceipt {
                return SePayWebhookReceipt::query()->create([
                    'environment' => $environment,
                    'external_event_id' => $externalId,
                    'payload_hash' => $payloadHash,
                    'raw_payload' => $rawBody,
                    'request_headers' => $headers,
                    'status' => 'received',
                    'attempt_count' => 0,
                    'received_at' => now(),
                ]);
            }, 3);
        } catch (QueryException $exception) {
            $receipt = SePayWebhookReceipt::query()
                ->where('environment', $environment)
                ->where('external_event_id', $externalId)
                ->first();

            if (! $receipt) {
                throw $exception;
            }

            if (in_array($receipt->status, ['received', 'failed'], true)) {
                $this->dispatch($receipt);
            }

            return ['status' => 'duplicate', 'receipt' => $receipt->fresh()];
        }

        $this->dispatch($receipt);

        return ['status' => 'accepted_new', 'receipt' => $receipt->fresh()];
    }

    private function dispatch(SePayWebhookReceipt $receipt): void
    {
        try {
            ProcessSePayWebhookJob::dispatch($receipt->getKey())
                ->onConnection(config('queue.default'))
                ->onQueue(config('sepay.queues.payments', 'payments'));

            $receipt->forceFill([
                'status' => 'queued',
                'attempt_count' => ((int) $receipt->attempt_count) + 1,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $receipt->forceFill([
                'status' => 'failed',
                'attempt_count' => ((int) $receipt->attempt_count) + 1,
                'error_message' => 'dispatch_failed',
            ])->save();

            throw $exception;
        }
    }

    /** @return array<string, string> */
    private function auditHeaders(Request $request): array
    {
        $headers = [];
        foreach (['content-type', 'x-sepay-timestamp', 'x-request-id', 'user-agent', 'cf-ray'] as $name) {
            $value = $request->header($name);
            if (is_string($value) && $value !== '') {
                $headers[$name] = mb_substr($value, 0, 255);
            }
        }

        return $headers;
    }
}
