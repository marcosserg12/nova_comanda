<?php

namespace App\Queries;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Setting;

final class BoardQuery
{
    /** @return array{preparing: list<array{id: int, number: string, first_name: string}>, ready: list<array{id: int, number: string, first_name: string}>} */
    public function get(): array
    {
        $minutes = (int) Setting::get(Setting::READY_MINUTES);
        $toEntry = fn (Order $order): array => [
            'id' => $order->id,
            'number' => $order->displayNumber(),
            'first_name' => $order->firstName(),
        ];

        $preparing = Order::query()->today()
            ->where('status', OrderStatus::Preparing->value)
            ->orderBy('id')
            ->get(['id', 'number', 'customer_name'])
            ->map($toEntry)->values()->all();

        $ready = Order::query()->today()
            ->where('status', OrderStatus::Ready->value)
            ->where('ready_at', '>=', now()->subMinutes($minutes))
            ->orderByDesc('ready_at')
            ->get(['id', 'number', 'customer_name'])
            ->map($toEntry)->values()->all();

        return ['preparing' => $preparing, 'ready' => $ready];
    }
}
