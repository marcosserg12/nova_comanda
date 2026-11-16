<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
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
        $built = $this->builder->handle($data['items']);

        $kind = DB::transaction(function () use ($order, $data, $built) {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OrderStatus::Preparing) {
                throw ValidationException::withMessages(['order' => 'Este pedido não pode mais ser alterado.']);
            }

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

        return $fresh;
    }
}
