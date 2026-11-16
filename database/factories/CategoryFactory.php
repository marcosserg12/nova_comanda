<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'destination' => Destination::Cozinha,
            'position' => 0,
            'active' => true,
        ];
    }
}
