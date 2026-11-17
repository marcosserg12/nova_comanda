<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\CustomerName;
use App\Models\Order;
use App\Support\OrderBroadcaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class UpdateOrderItems
{
    public function __construct(private BuildOrderItems $builder, private OrderBroadcaster $broadcaster) {}

    public function handle(Order $order, array $input): Order
    {
        $data = Validator::make($input, OrderInput::rules(), OrderInput::messages())->validate();

        $kind = DB::transaction(function () use ($order, $data) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Preparing) {
                throw ValidationException::withMessages(['order' => 'Este pedido não pode mais ser alterado.']);
            }

            // Lines already in the order keep their ordered name, price and destination; only new products are validated.
            $built = $this->builder->handle($data['items'], $locked->items()->get());

            $locked->items()->delete();
            $locked->items()->createMany($built['items']);

            $locked->fill([
                'customer_name' => trim($data['customer_name']),
                'takeaway' => (bool) ($data['takeaway'] ?? false),
                'total_cents' => $built['total_cents'],
                'edited_at' => now(),
            ]);

            if (! $built['has_kitchen']) {
                $locked->status = OrderStatus::Delivered;
                $locked->delivered_at = now();
            }

            $locked->save();

            return $built['has_kitchen'] ? 'updated' : 'delivered';
        });

        $fresh = $order->fresh(['items']);
        $this->broadcaster->changed($fresh, $kind);

        if (CustomerName::keyFor($fresh->customer_name) !== CustomerName::keyFor($order->customer_name)) {
            rescue(fn () => CustomerName::record($fresh->customer_name));
        }

        return $fresh;
    }
}
