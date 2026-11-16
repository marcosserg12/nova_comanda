<?php

namespace Database\Factories;

use App\Enums\Destination;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'product_id' => null,
            'name_snapshot' => ucfirst(fake()->words(2, true)),
            'unit_price_cents' => 2400,
            'quantity' => 1,
            'notes' => null,
            'destination' => Destination::Cozinha,
            'position' => 0,
        ];
    }
}
