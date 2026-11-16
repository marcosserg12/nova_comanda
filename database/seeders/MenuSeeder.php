<?php

namespace Database\Seeders;

use App\Enums\Destination;
use App\Models\Category;
use App\Models\Product;
use App\Models\QuickNote;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /** @var list<array{0: string, 1: int, 2: bool}> name, price in cents, has daily name */
    private const DISHES = [
        ['Prato do dia', 2600, true],
        ['Prato Econômico', 1700, true],
        ['Frango ao molho', 2300, false],
        ['Fígado bovino', 2200, false],
        ['Omelete especial', 2200, false],
        ['Filé de frango', 2400, false],
        ['Filé suíno', 2200, false],
        ['Frango à milanesa', 2500, false],
        ['Parmegiana de frango', 2700, false],
        ['Filé de peixe', 2900, false],
        ['Carne de sol', 3200, false],
        ['Coxão mole', 3400, false],
        ['Parmegiana de carne', 3600, false],
        ['Filé mignon', 4000, false],
        ['Picanha bovina', 3800, false],
    ];

    private const JUICES = [['Suco de limão', 400], ['Suco de goiaba', 500], ['Suco de manga', 600]];

    private const DISH_NOTES = ['Sem arroz', 'Sem feijão', 'Sem fritas', 'Sem farofa', 'Sem salada', 'C/ Ovo', 'C/ Cebola'];

    public function run(): void
    {
        $pratos = Category::query()->firstOrCreate(['name' => 'Pratos'], ['destination' => Destination::Cozinha, 'position' => 1]);
        $sucos = Category::query()->firstOrCreate(['name' => 'Sucos'], ['destination' => Destination::Cozinha, 'position' => 2]);
        Category::query()->firstOrCreate(['name' => 'Bebidas'], ['destination' => Destination::Cabine, 'position' => 3]);

        foreach (self::DISHES as $index => [$name, $cents, $daily]) {
            Product::query()->firstOrCreate(
                ['category_id' => $pratos->id, 'name' => $name],
                ['price_cents' => $cents, 'has_daily_name' => $daily, 'position' => $index + 1],
            );
        }

        foreach (self::JUICES as $index => [$name, $cents]) {
            Product::query()->firstOrCreate(
                ['category_id' => $sucos->id, 'name' => $name],
                ['price_cents' => $cents, 'position' => $index + 1],
            );
        }

        foreach (self::DISH_NOTES as $index => $label) {
            QuickNote::query()->firstOrCreate(['category_id' => $pratos->id, 'label' => $label], ['position' => $index + 1]);
        }

        QuickNote::query()->firstOrCreate(['category_id' => $sucos->id, 'label' => 'Sem açúcar'], ['position' => 1]);
    }
}
