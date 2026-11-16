<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'price_cents' => fake()->numberBetween(400, 4000),
            'has_daily_name' => false,
            'active' => true,
            'position' => 0,
        ];
    }
}
