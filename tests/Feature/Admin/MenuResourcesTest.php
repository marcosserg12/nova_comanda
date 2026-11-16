<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessDay;
use Filament\Actions\DeleteAction;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs($this->admin = userWithRole(Role::Admin)));

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

it('updates the price inline in reais', function () {
    $product = kitchenProduct(['price_cents' => 2400]);

    livewire(ListProducts::class)->call('updateTableColumnState', 'price', (string) $product->getKey(), '24,50');

    expect($product->fresh()->price_cents)->toBe(2450);
});

it('rejects an invalid inline price', function () {
    $product = kitchenProduct(['price_cents' => 2400]);

    livewire(ListProducts::class)->call('updateTableColumnState', 'price', (string) $product->getKey(), 'abc');

    expect($product->fresh()->price_cents)->toBe(2400);
});

it('toggles sold out today inline', function () {
    $product = kitchenProduct();

    livewire(ListProducts::class)->call('updateTableColumnState', 'sold_out_today', (string) $product->getKey(), true);
    expect($product->fresh()->isSoldOut())->toBeTrue()
        ->and($product->fresh()->sold_out_date->toDateString())->toBe(BusinessDay::todayString());

    livewire(ListProducts::class)->call('updateTableColumnState', 'sold_out_today', (string) $product->getKey(), false);
    expect($product->fresh()->isSoldOut())->toBeFalse()
        ->and($product->fresh()->sold_out_date)->toBeNull();
});

it('keeps the admin role when an admin edits themselves, but lets other roles be edited', function () {
    livewire(EditUser::class, ['record' => $this->admin->getRouteKey()])
        ->fillForm(['name' => 'Novo Nome', 'role' => 'balcao'])
        ->call('save')->assertHasNoFormErrors();

    expect($this->admin->fresh()->role)->toBe(Role::Admin)->and($this->admin->fresh()->name)->toBe('Novo Nome');

    $other = userWithRole(Role::Balcao);
    livewire(EditUser::class, ['record' => $other->getRouteKey()])
        ->fillForm(['role' => 'cozinha'])->call('save')->assertHasNoFormErrors();

    expect($other->fresh()->role)->toBe(Role::Cozinha);
});

it('does not let a category with products be deleted, and deletes an empty one', function () {
    $used = Category::factory()->create();
    Product::factory()->for($used)->create();
    $empty = Category::factory()->create();

    livewire(EditCategory::class, ['record' => $used->getRouteKey()])
        ->assertActionDisabled(DeleteAction::class);
    livewire(EditCategory::class, ['record' => $used->getRouteKey()])
        ->callAction(DeleteAction::class)
        ->assertOk();
    expect(Category::query()->whereKey($used->id)->exists())->toBeTrue();

    livewire(EditCategory::class, ['record' => $empty->getRouteKey()])
        ->assertActionEnabled(DeleteAction::class)
        ->callAction(DeleteAction::class);
    expect(Category::query()->whereKey($empty->id)->exists())->toBeFalse();
});
