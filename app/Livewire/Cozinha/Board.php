<?php

namespace App\Livewire\Cozinha;

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Queries\KitchenQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Board extends Component
{
    #[Computed]
    public function orders(): Collection
    {
        return app(KitchenQueue::class)->get();
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
    }

    public function markReadyByNumber(int $number): ?int
    {
        Gate::authorize('mark-ready');

        $order = Order::query()->today()
            ->where('number', $number)
            ->where('status', OrderStatus::Preparing->value)
            ->first();

        if ($order === null) {
            $this->dispatch('kitchen-not-found', number: str_pad((string) $number, 3, '0', STR_PAD_LEFT));

            return null;
        }

        app(TransitionOrder::class)->markReady($order);

        return $order->id;
    }

    public function undoReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->undoReady(Order::query()->findOrFail($orderId), auth()->user());
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(array $payload = []): void
    {
        if (in_array($payload['kind'] ?? null, ['created', 'updated'], true)) {
            $this->dispatch('kitchen-beep');
        }
    }

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.cozinha.board');
    }
}
