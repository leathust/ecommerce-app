<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentAttempt> */
class PaymentAttemptFactory extends Factory
{
    protected $model = PaymentAttempt::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'order_id' => Order::factory(),
            'environment' => 'sandbox',
            'provider' => 'sepay',
            'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'idempotency_key' => hash('sha256', (string) Str::uuid()),
            'access_token_hash' => hash('sha256', Str::random(64)),
            'expected_amount' => fake()->numberBetween(100000, 5000000),
            'currency' => 'VND',
            'status' => 'pending',
            'bank_code' => 'TESTBANK',
            'bank_account_number' => fake()->numerify('############'),
            'bank_account_name' => fake()->name(),
            'expires_at' => now()->addMinutes(15),
            'metadata' => ['source' => 'factory'],
        ];
    }
}
