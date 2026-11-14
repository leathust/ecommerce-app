<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'session_token' => hash('sha256', fake()->unique()->uuid()),
        ];
    }

    public function forCustomer(User|int|null $user = null): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user instanceof User ? $user->getKey() : ($user ?? User::factory()),
            'session_token' => null,
        ]);
    }

    public function guest(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'session_token' => hash('sha256', fake()->unique()->uuid()),
        ]);
    }

    public function guestWithToken(string $rawToken): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'session_token' => hash('sha256', $rawToken),
        ]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->has(CartItem::factory()->count($count), 'items');
    }

    public function withUnavailableItem(int $quantity = 1): static
    {
        return $this->has(
            CartItem::factory()->unavailable()->quantity($quantity),
            'items',
        );
    }
}
