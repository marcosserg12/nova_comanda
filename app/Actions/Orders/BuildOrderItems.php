<?php

namespace App\Actions\Orders;

use App\Enums\Destination;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class BuildOrderItems
{
    /**
     * @param  list<array{product_id: int|string, quantity: int|string, notes?: ?string, item_id?: int|string|null}>  $items
     * @param  Collection<int, OrderItem>|null  $existing  items already in the order being edited: their
     *                                                     snapshot (name, price, destination) is reused and never re-validated
     * @return array{items: list<array<string, mixed>>, total_cents: int, has_kitchen: bool}
     */
    public function handle(array $items, ?Collection $existing = null): array
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
            $quantity = (int) $item['quantity'];
            $notes = trim((string) ($item['notes'] ?? ''));
            $kept = $this->existingFor($item, $existing);

            if ($kept !== null) {
                $rows[] = [
                    'product_id' => $kept->product_id,
                    'name_snapshot' => $kept->name_snapshot,
                    'unit_price_cents' => $kept->unit_price_cents,
                    'quantity' => $quantity,
                    'notes' => $notes === '' ? null : $notes,
                    'destination' => $kept->destination,
                    'position' => $index,
                ];

                $total += $quantity * $kept->unit_price_cents;
                $hasKitchen = $hasKitchen || $kept->destination === Destination::Cozinha;

                continue;
            }

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

    /** @param  Collection<int, OrderItem>|null  $existing */
    private function existingFor(array $item, ?Collection $existing): ?OrderItem
    {
        if ($existing === null) {
            return null;
        }

        $byId = ($item['item_id'] ?? null) !== null ? $existing->firstWhere('id', (int) $item['item_id']) : null;

        return $byId ?? ((int) $item['product_id'] > 0 ? $existing->firstWhere('product_id', (int) $item['product_id']) : null);
    }
}
