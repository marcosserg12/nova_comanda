<?php

namespace App\Queries;

use App\Enums\Destination;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class KitchenQueue
{
    /** @return Collection<int, Order> */
    public function get(): Collection
    {
        return Order::query()->today()
            ->where('status', OrderStatus::Preparing->value)
            ->with(['items' => fn ($query) => $query->where('destination', Destination::Cozinha->value)])
            ->whereHas('items', fn ($query) => $query->where('destination', Destination::Cozinha->value))
            ->orderBy('id')
            ->get();
    }
}
