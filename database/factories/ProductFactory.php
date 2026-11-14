<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'brand_id' => null,
            'sku' => fake()->unique()->bothify('TEST-####-????'),
            'name_vi' => 'Sản phẩm '.$name,
            'name_en' => 'Product '.$name,
            'slug' => fake()->unique()->slug(3),
            'description_vi' => null,
            'description_en' => null,
            'price' => fake()->numberBetween(100, 50000) * 1000,
            'quantity' => fake()->numberBetween(1, 100),
            'is_featured' => false,
            'status' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['quantity' => 0]);
    }
}
