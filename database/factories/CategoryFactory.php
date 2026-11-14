<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'parent_id' => null,
            'name_vi' => 'Danh mục '.$name,
            'name_en' => 'Category '.$name,
            'slug' => fake()->unique()->slug(2),
            'image_url' => null,
            'sort_order' => 0,
            'status' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => false]);
    }
}
