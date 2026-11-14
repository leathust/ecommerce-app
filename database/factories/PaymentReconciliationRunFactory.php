<?php

namespace Database\Factories;

use App\Models\PaymentReconciliationRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<PaymentReconciliationRun> */
class PaymentReconciliationRunFactory extends Factory
{
    protected $model = PaymentReconciliationRun::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'environment' => 'sandbox',
            'status' => 'pending',
            'fetched_count' => 0,
            'matched_count' => 0,
            'review_count' => 0,
            'failed_count' => 0,
            'metadata' => ['source' => 'factory'],
        ];
    }
}
