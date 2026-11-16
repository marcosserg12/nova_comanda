<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\TransitionOrder;
use App\Models\Order;
use App\Queries\KitchenQueue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class InKitchenList extends Component
{
    #[Computed]
    public function orders(): Collection
    {
        return app(KitchenQueue::class)->get();
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        $order = app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
        $this->dispatch('toast', type: 'success', message: "Pedido {$order->displayNumber()} pronto, chamando na TV.");
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(): void {}

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.balcao.in-kitchen-list');
    }
}
