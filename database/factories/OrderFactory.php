<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        $subtotal = fake()->numberBetween(100000, 5000000);
        $shippingFee = fake()->randomElement([0, 20000, 30000]);

        return [
            'order_code' => 'ORD-'.fake()->unique()->bothify('########??'),
            'user_id' => null,
            'status' => 'pending',
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'subtotal' => $subtotal,
            'shipping_fee' => $shippingFee,
            'total_amount' => $subtotal + $shippingFee,
            'recipient_name' => fake()->name(),
            'recipient_phone' => '09'.fake()->numerify('########'),
            'shipping_address' => fake()->address(),
        ];
    }
}
