<?php

namespace Database\Factories;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'business_date' => BusinessDay::todayString(),
            'number' => fake()->unique()->numberBetween(1, 999),
            'customer_name' => fake()->firstName(),
            'takeaway' => false,
            'status' => OrderStatus::Preparing,
            'source' => OrderSource::Balcao,
            'total_cents' => 0,
        ];
    }

    public function ready(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Ready, 'ready_at' => now()]);
    }
}
