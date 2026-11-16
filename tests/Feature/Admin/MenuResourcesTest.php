<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(userWithRole(Role::Admin)));

it('lists products with the price in reais', function () {
    $product = kitchenProduct(['name' => 'Carne de sol', 'price_cents' => 3200]);

    livewire(ListProducts::class)->assertOk()->assertCanSeeTableRecords([$product])->assertSee('32,00');
});

it('creates a product typing the price in reais', function () {
    $category = Category::factory()->create(['destination' => Destination::Cozinha]);

    livewire(CreateProduct::class)
        ->fillForm(['category_id' => $category->id, 'name' => 'Feijoada', 'price' => '29,90', 'active' => true, 'position' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Product::query()->where('name', 'Feijoada')->value('price_cents'))->toBe(2990);
});

it('fills the edit form with the current price and saves a new one', function () {
    $product = kitchenProduct(['price_cents' => 2400]);

    livewire(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertSchemaStateSet(['price' => '24,00'])
        ->fillForm(['price' => '25'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->price_cents)->toBe(2500);
});

it('creates categories and users', function () {
    livewire(CreateCategory::class)
        ->fillForm(['name' => 'Sobremesas', 'destination' => 'cozinha', 'position' => 4, 'active' => true])
        ->call('create')->assertHasNoFormErrors();

    livewire(CreateUser::class)
        ->fillForm(['name' => 'Cozinha', 'username' => 'cozinha', 'role' => 'cozinha', 'password' => 'segredo123'])
        ->call('create')->assertHasNoFormErrors();

    expect(Category::query()->where('name', 'Sobremesas')->exists())->toBeTrue()
        ->and(User::query()->where('username', 'cozinha')->sole()->role)->toBe(Role::Cozinha);
});
