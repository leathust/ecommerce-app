<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPaymentConfirmed implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $broadcastQueue;

    public function __construct(
        public readonly string $paymentPublicId,
        public readonly string $status,
        public readonly string $paidAt,
    ) {
        $this->broadcastQueue = (string) config('sepay.queues.broadcasts', 'broadcasts');
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('payment.'.$this->paymentPublicId);
    }

    public function broadcastAs(): string
    {
        return 'payment.confirmed';
    }

    /** @return array{payment_public_id: string, status: string, paid_at: string} */
    public function broadcastWith(): array
    {
        return [
            'payment_public_id' => $this->paymentPublicId,
            'status' => $this->status,
            'paid_at' => $this->paidAt,
        ];
    }
}
