<?php

namespace App\Support;

use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Throwable;

final class OrderBroadcaster
{
    public function changed(Order $order, string $kind): void
    {
        $orderId = $order->id;
        $number = $order->displayNumber();
        $firstName = $order->firstName();
        $status = $order->status->value;

        DB::afterCommit(function () use ($orderId, $kind, $number, $firstName, $status) {
            try {
                broadcast(new OrdersChanged($orderId, $kind));
                broadcast(new BoardChanged($orderId, $kind, $number, $firstName, $status));
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
