<?php

namespace App\Actions\Orders;

use App\Enums\Destination;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class BuildOrderItems
{
    /**
     * @param  list<array{product_id: int|string, quantity: int|string, notes?: ?string}>  $items
     * @return array{items: list<array<string, mixed>>, total_cents: int, has_kitchen: bool}
     */
    public function handle(array $items): array
    {
        $products = Product::query()
            ->with('category')
            ->whereIn('id', array_map(fn (array $item) => (int) $item['product_id'], $items))
            ->get()
            ->keyBy('id');

        $rows = [];
        $total = 0;
        $hasKitchen = false;

        foreach (array_values($items) as $index => $item) {
            $product = $products->get((int) $item['product_id']);

            $error = match (true) {
                $product === null => 'Produto não encontrado.',
                ! $product->active || ! $product->category->active => "{$product->name} não está disponível.",
                $product->isSoldOut() => "{$product->name} está esgotado hoje.",
                $product->needsDailyName() => "Defina o prato de hoje para {$product->name}.",
                default => null,
            };

            if ($error !== null) {
                throw ValidationException::withMessages(["items.{$index}.product_id" => $error]);
            }

            $quantity = (int) $item['quantity'];
            $notes = trim((string) ($item['notes'] ?? ''));
            $destination = $product->category->destination;

            $rows[] = [
                'product_id' => $product->id,
                'name_snapshot' => $product->displayName(),
                'unit_price_cents' => $product->price_cents,
                'quantity' => $quantity,
                'notes' => $notes === '' ? null : $notes,
                'destination' => $destination,
                'position' => $index,
            ];

            $total += $quantity * $product->price_cents;
            $hasKitchen = $hasKitchen || $destination === Destination::Cozinha;
        }

        return ['items' => $rows, 'total_cents' => $total, 'has_kitchen' => $hasKitchen];
    }
}
