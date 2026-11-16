<?php

namespace App\Queries;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

final class TodayOrdersQuery
{
    /** @return Collection<int, Order> */
    public function search(string $term = ''): Collection
    {
        $term = trim($term);

        return Order::query()->today()
            ->with('items')
            ->when($term !== '' && ctype_digit($term), fn ($query) => $query->where('number', (int) $term))
            ->when($term !== '' && ! ctype_digit($term), fn ($query) => $query->where('customer_name', 'like', "%{$term}%"))
            ->orderByDesc('id')
            ->limit(200)
            ->get();
    }
}
