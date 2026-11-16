<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
