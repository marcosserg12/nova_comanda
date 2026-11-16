<?php

use App\Enums\Destination;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuickNote;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

it('seeds the Nana Lanches menu and the initial users', function () {
    $this->seed(DatabaseSeeder::class);

    $pratos = Category::query()->where('name', 'Pratos')->sole();
    $sucos = Category::query()->where('name', 'Sucos')->sole();

    expect($pratos->destination)->toBe(Destination::Cozinha)
        ->and(Category::query()->where('name', 'Bebidas')->sole()->destination)->toBe(Destination::Cabine)
        ->and($pratos->products()->count())->toBe(15)
        ->and($sucos->products()->pluck('price_cents', 'name')->all())
        ->toBe(['Suco de limão' => 400, 'Suco de goiaba' => 500, 'Suco de manga' => 600])
        ->and(Product::query()->where('name', 'Prato do dia')->sole()->has_daily_name)->toBeTrue()
        ->and(Product::query()->where('name', 'Picanha bovina')->sole()->price_cents)->toBe(3800)
        ->and(QuickNote::query()->where('category_id', $pratos->id)->orderBy('position')->pluck('label')->all())
        ->toBe(['Sem arroz', 'Sem feijão', 'Sem fritas', 'Sem farofa', 'Sem salada', 'C/ Ovo', 'C/ Cebola'])
        ->and(User::query()->where('username', 'admin')->sole()->role)->toBe(Role::Admin)
        ->and(User::query()->where('username', 'balcao')->sole()->role)->toBe(Role::Balcao);
});

it('can run twice without duplicating or overwriting prices', function () {
    $this->seed(DatabaseSeeder::class);
    Product::query()->where('name', 'Picanha bovina')->update(['price_cents' => 4200]);

    $this->seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBe(18)
        ->and(User::query()->count())->toBe(2)
        ->and(Product::query()->where('name', 'Picanha bovina')->value('price_cents'))->toBe(4200);
});
