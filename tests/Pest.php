<?php

use App\Actions\Orders\PlaceOrder;
use App\Enums\Destination;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

function userWithRole(Role $role): User
{
    return User::factory()->create(['role' => $role]);
}

function kitchenProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->state(['destination' => Destination::Cozinha]))
        ->create($attributes);
}

function cabineProduct(array $attributes = []): Product
{
    return Product::factory()
        ->for(Category::factory()->state(['destination' => Destination::Cabine]))
        ->create($attributes);
}

/** @return array{product_id: int, quantity: int, notes: ?string} */
function item(Product $product, int $quantity = 1, ?string $notes = null): array
{
    return ['product_id' => $product->id, 'quantity' => $quantity, 'notes' => $notes];
}

function orderInput(array $items, array $overrides = []): array
{
    return array_merge(['uuid' => (string) Str::uuid(), 'customer_name' => 'Maria', 'takeaway' => false, 'items' => $items], $overrides);
}

function placeOrder(array $items, array $overrides = [], ?User $by = null): Order
{
    return app(PlaceOrder::class)->handle(orderInput($items, $overrides), $by);
}
