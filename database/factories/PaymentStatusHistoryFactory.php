<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentStatusHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentStatusHistory> */
class PaymentStatusHistoryFactory extends Factory
{
    protected $model = PaymentStatusHistory::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'from_status' => null,
            'to_status' => 'unpaid',
            'source' => 'system',
            'note' => null,
            'metadata' => ['source' => 'factory'],
            'created_at' => now(),
        ];
    }
}
