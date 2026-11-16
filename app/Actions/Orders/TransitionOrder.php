<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use App\Support\OrderBroadcaster;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class TransitionOrder
{
    public const UNDO_WINDOW_SECONDS = 15;

    public function __construct(private OrderBroadcaster $broadcaster) {}

    public function markReady(Order $order): Order
    {
        return $this->move($order, [OrderStatus::Preparing], OrderStatus::Ready, ['ready_at' => now()], 'ready');
    }

    public function undoReady(Order $order, User $by): Order
    {
        if ($by->hasRole(Role::Cozinha)) {
            $fresh = $order->fresh();
            if ($fresh->status === OrderStatus::Ready
                && $fresh->ready_at !== null
                && $fresh->ready_at->lt(now()->subSeconds(self::UNDO_WINDOW_SECONDS))) {
                throw new AuthorizationException('O tempo para desfazer acabou.');
            }
        }

        return $this->move($order, [OrderStatus::Ready], OrderStatus::Preparing, ['ready_at' => null], 'unready');
    }

    public function markDelivered(Order $order): Order
    {
        return $this->move($order, [OrderStatus::Ready], OrderStatus::Delivered, ['delivered_at' => now()], 'delivered');
    }

    public function cancel(Order $order, string $reason): Order
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 120) {
            throw ValidationException::withMessages(['cancelReason' => 'Informe o motivo do cancelamento (até 120 caracteres).']);
        }

        return $this->move(
            $order,
            [OrderStatus::Preparing, OrderStatus::Ready],
            OrderStatus::Canceled,
            ['canceled_at' => now(), 'cancel_reason' => $reason],
            'canceled',
        );
    }

    /**
     * Atomic conditional update: concurrent or repeated taps update at most once.
     *
     * @param  list<OrderStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function move(Order $order, array $from, OrderStatus $to, array $attributes, string $kind): Order
    {
        $updated = Order::query()
            ->whereKey($order->getKey())
            ->whereIn('status', array_map(fn (OrderStatus $status) => $status->value, $from))
            ->update([...$attributes, 'status' => $to->value, 'updated_at' => now()]);

        $fresh = $order->fresh(['items']);

        if ($updated === 1) {
            $this->broadcaster->changed($fresh, $kind);
        }

        return $fresh;
    }
}
