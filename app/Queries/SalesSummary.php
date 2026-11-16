<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;

final class SalesSummary
{
    /** @return array{total_cents: int, orders: int, average_cents: int, by_product: list<array{name: string, quantity: int, total_cents: int}>} */
    public function between(CarbonImmutable $from, CarbonImmutable $until): array
    {
        $base = Order::query()
            ->whereDate('business_date', '>=', $from->toDateString())
            ->whereDate('business_date', '<=', $until->toDateString())
            ->where('status', '!=', OrderStatus::Canceled->value);

        $orders = (clone $base)->count();
        $total = (int) (clone $base)->sum('total_cents');

        $byProduct = OrderItem::query()
            ->whereIn('order_id', (clone $base)->select('id'))
            ->selectRaw('name_snapshot as name, SUM(quantity) as qty, SUM(quantity * unit_price_cents) as cents')
            ->groupBy('name_snapshot')
            ->orderByDesc('qty')
            ->orderBy('name_snapshot')
            ->get()
            ->map(fn ($row) => ['name' => (string) $row->name, 'quantity' => (int) $row->qty, 'total_cents' => (int) $row->cents])
            ->all();

        return [
            'total_cents' => $total,
            'orders' => $orders,
            'average_cents' => $orders > 0 ? intdiv($total, $orders) : 0,
            'by_product' => $byProduct,
        ];
    }
}
