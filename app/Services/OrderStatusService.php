<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(private readonly OrderInventoryService $inventory) {}

    public function transition(Order $order, string $targetStatus, User $actor, ?string $note = null, ?string $cancelReason = null): Order
    {
        return DB::transaction(function () use ($order, $targetStatus, $actor, $note, $cancelReason): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            if (! $lockedOrder->canTransitionTo($targetStatus)) {
                throw ValidationException::withMessages([
                    'status' => 'Không thể chuyển đơn từ "'.(Order::STATUSES[$lockedOrder->status] ?? $lockedOrder->status).'" sang "'.(Order::STATUSES[$targetStatus] ?? $targetStatus).'".',
                ]);
            }

            $fromStatus = $lockedOrder->status;
            $changedAt = now();
            if ($targetStatus === 'cancelled') {
                $this->inventory->restore($lockedOrder, $changedAt);
            } elseif (! $lockedOrder->stock_deducted_at) {
                $this->inventory->deduct($lockedOrder, $changedAt);
            }
            $updates = ['status' => $targetStatus];
            $timestampField = [
                'confirmed' => 'confirmed_at', 'processing' => 'processing_at',
                'shipped' => 'shipped_at', 'delivered' => 'delivered_at', 'cancelled' => 'cancelled_at',
            ][$targetStatus] ?? null;
            if ($timestampField) { $updates[$timestampField] = $changedAt; }
            if ($targetStatus === 'cancelled') { $updates['cancel_reason'] = $cancelReason; }
            if ($lockedOrder->stock_deducted_at) { $updates['stock_deducted_at'] = $lockedOrder->stock_deducted_at; }
            if ($lockedOrder->stock_restored_at) { $updates['stock_restored_at'] = $lockedOrder->stock_restored_at; }

            $lockedOrder->update($updates);
            $lockedOrder->statusHistories()->create([
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'changed_by' => $actor->id,
                'note' => $note ?: ($targetStatus === 'cancelled' ? $cancelReason : null),
                'created_at' => $changedAt,
            ]);

            return $lockedOrder->fresh(['user', 'items', 'statusHistories.changedBy']);
        });
    }
}
