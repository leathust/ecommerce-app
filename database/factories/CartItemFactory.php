<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartItem>
 */
class CartItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'product_id' => Product::factory()->state(['quantity' => 10]),
            'quantity' => fake()->numberBetween(1, 3),
        ];
    }

    public function quantity(int $quantity): static
    {
        return $this->state(fn (): array => ['quantity' => max(1, $quantity)]);
    }

    public function unavailable(): static
    {
        return $this->for(Product::factory()->outOfStock(), 'product');
    }

    public function inactive(): static
    {
        return $this->for(Product::factory()->inactive(), 'product');
    }
}
