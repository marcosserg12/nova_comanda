<?php

namespace App\Actions\Orders;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\OrderBroadcaster;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class PlaceOrder
{
    public function __construct(
        private NextOrderNumber $numbers,
        private BuildOrderItems $builder,
        private OrderBroadcaster $broadcaster,
    ) {}

    public function handle(array $input, ?User $by = null, OrderSource $source = OrderSource::Balcao): Order
    {
        $data = Validator::make($input, ['uuid' => ['required', 'uuid'], ...OrderInput::rules()], OrderInput::messages())->validate();

        $existing = Order::query()->where('uuid', $data['uuid'])->first();
        if ($existing !== null) {
            return $existing->load('items');
        }

        $built = $this->builder->handle($data['items']);

        try {
            // Concurrent orders can deadlock on the daily counter row; Laravel retries the closure.
            $order = DB::transaction(function () use ($data, $built, $by, $source) {
                $day = BusinessDay::today();

                $order = Order::query()->create([
                    'uuid' => $data['uuid'],
                    'business_date' => $day->toDateString(),
                    'number' => $this->numbers->for($day),
                    'customer_name' => trim($data['customer_name']),
                    'takeaway' => (bool) ($data['takeaway'] ?? false),
                    'status' => $built['has_kitchen'] ? OrderStatus::Preparing : OrderStatus::Delivered,
                    'delivered_at' => $built['has_kitchen'] ? null : now(),
                    'source' => $source,
                    'total_cents' => $built['total_cents'],
                    'created_by' => $by?->id,
                ]);

                $order->items()->createMany($built['items']);

                return $order;
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $e) {
            $existing = Order::query()->where('uuid', $data['uuid'])->first();
            if ($existing === null) {
                throw $e;
            }

            return $existing->load('items');
        }

        $order->load('items');
        $this->broadcaster->changed($order, $order->status === OrderStatus::Preparing ? 'created' : 'delivered');

        return $order;
    }
}
