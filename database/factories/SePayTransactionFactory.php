<?php

namespace Database\Factories;

use App\Models\SePayTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SePayTransaction> */
class SePayTransactionFactory extends Factory
{
    protected $model = SePayTransaction::class;

    public function definition(): array
    {
        return [
            'environment' => 'sandbox',
            'external_transaction_id' => (string) Str::uuid(),
            'gateway' => 'TESTBANK',
            'account_number' => fake()->numerify('############'),
            'direction' => 'in',
            'amount' => fake()->numberBetween(100000, 5000000),
            'currency' => 'VND',
            'transfer_content' => 'DH'.Str::upper(Str::random(12)),
            'transaction_at' => now(),
            'match_status' => 'unmatched',
            'raw_data' => ['source' => 'factory'],
        ];
    }
}
