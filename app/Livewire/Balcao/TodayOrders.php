<?php

namespace App\Livewire\Balcao;

use App\Actions\Orders\TransitionOrder;
use App\Models\Order;
use App\Queries\TodayOrdersQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class TodayOrders extends Component
{
    public const CANCEL_REASONS = ['Cliente desistiu', 'Lançado errado', 'Outro'];

    public string $search = '';

    #[Locked]
    public ?int $cancelingOrderId = null;

    public string $cancelReason = '';

    public string $cancelReasonOther = '';

    #[Computed]
    public function orders(): Collection
    {
        return app(TodayOrdersQuery::class)->search($this->search);
    }

    public function markReady(int $orderId): void
    {
        Gate::authorize('mark-ready');
        app(TransitionOrder::class)->markReady(Order::query()->findOrFail($orderId));
    }

    public function markDelivered(int $orderId): void
    {
        Gate::authorize('manage-orders');
        app(TransitionOrder::class)->markDelivered(Order::query()->findOrFail($orderId));
    }

    public function backToPreparing(int $orderId): void
    {
        Gate::authorize('manage-orders');
        app(TransitionOrder::class)->undoReady(Order::query()->findOrFail($orderId), auth()->user());
    }

    public function edit(int $orderId): void
    {
        Gate::authorize('manage-orders');
        $this->dispatch('edit-order', orderId: $orderId);
    }

    public function startCancel(int $orderId): void
    {
        Gate::authorize('manage-orders');
        $this->cancelingOrderId = $orderId;
        $this->cancelReason = '';
        $this->cancelReasonOther = '';
        $this->resetErrorBag();
    }

    public function closeCancel(): void
    {
        $this->cancelingOrderId = null;
    }

    public function confirmCancel(): void
    {
        Gate::authorize('manage-orders');
        $this->validate(
            [
                'cancelReason' => ['required', Rule::in(self::CANCEL_REASONS)],
                'cancelReasonOther' => ['nullable', 'required_if:cancelReason,Outro', 'string', 'max:100'],
            ],
            ['cancelReason.required' => 'Escolha o motivo.', 'cancelReasonOther.required_if' => 'Descreva o motivo.'],
        );

        $reason = $this->cancelReason === 'Outro' ? 'Outro: '.trim($this->cancelReasonOther) : $this->cancelReason;
        $order = app(TransitionOrder::class)->cancel(Order::query()->findOrFail($this->cancelingOrderId), $reason);

        $this->cancelingOrderId = null;
        $this->dispatch('toast', type: 'success', message: "Pedido {$order->displayNumber()} cancelado.");
    }

    #[On('echo-private:pedidos,OrdersChanged')]
    public function onOrdersChanged(): void {}

    #[On('realtime-reconnected')]
    public function onReconnected(): void {}

    public function render(): View
    {
        return view('livewire.balcao.today-orders');
    }
}
