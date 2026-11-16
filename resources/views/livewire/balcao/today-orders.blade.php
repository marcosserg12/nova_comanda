@use('App\Enums\OrderStatus')
@use('App\Support\Money')
<div wire:poll.20s class="flex h-full min-h-0 flex-col gap-3">
    <input type="search" wire:model.live.debounce.200ms="search" placeholder="Buscar por número ou nome…" aria-label="Buscar pedido"
           class="min-h-12 w-full rounded-lg border-2 border-nana-ink bg-white px-4 text-lg font-semibold placeholder:text-nana-muted">

    <div class="min-h-0 flex-1 overflow-y-auto rounded-lg border-2 border-nana-ink bg-white shadow-hard">
        <ul class="divide-y-2 divide-nana-line">
            @forelse ($this->orders as $order)
                @php
                    $open = in_array($order->status, [OrderStatus::Preparing, OrderStatus::Ready], true);
                    $tone = match ($order->status) {
                        OrderStatus::Preparing => 'yellow',
                        OrderStatus::Ready => 'green',
                        OrderStatus::Delivered => 'outline',
                        OrderStatus::Canceled => 'red',
                    };
                @endphp
                <li wire:key="today-{{ $order->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 py-3">
                    <span class="w-14 font-display text-2xl leading-none tabular-nums">{{ $order->displayNumber() }}</span>
                    <span class="min-w-0 flex-1 basis-40">
                        <span class="block truncate text-lg font-bold">{{ $order->customer_name }}</span>
                        <span class="block text-sm font-semibold tabular-nums text-nana-muted">
                            {{ $order->created_at->format('H:i') }}
                            @if ($open)
                                · há {{ (int) floor(abs($order->created_at->diffInMinutes(now()))) }} min
                            @endif
                        </span>
                    </span>
                    @if ($order->takeaway)
                        <x-ui.badge tone="ink">VIAGEM</x-ui.badge>
                    @endif
                    <x-ui.badge :tone="$tone">{{ $order->status->label() }}</x-ui.badge>
                    <span class="w-24 text-right font-display text-lg tabular-nums">{{ Money::format($order->total_cents) }}</span>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($order->status === OrderStatus::Preparing)
                            <x-ui.button variant="success" wire:click="markReady({{ $order->id }})">Pronto</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="edit({{ $order->id }})">Editar</x-ui.button>
                        @elseif ($order->status === OrderStatus::Ready)
                            <x-ui.button variant="dark" wire:click="markDelivered({{ $order->id }})">Entregue</x-ui.button>
                            <x-ui.button variant="ghost" wire:click="backToPreparing({{ $order->id }})">Voltar p/ preparo</x-ui.button>
                        @endif
                        @if ($open)
                            <x-ui.button variant="ghost" class="text-nana-red" wire:click="startCancel({{ $order->id }})">Cancelar</x-ui.button>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-4 py-12 text-center font-semibold text-nana-muted">Nenhum pedido hoje.</li>
            @endforelse
        </ul>
    </div>

    @if ($cancelingOrderId)
        <div x-data x-init="$nextTick(() => $el.querySelector('input[type=radio]')?.focus())" x-on:keydown.escape.window="$wire.closeCancel()"
             class="fixed inset-0 z-40 flex items-center justify-center bg-nana-ink/60 p-4">
            <div role="dialog" aria-modal="true" aria-labelledby="cancel-title"
                 class="w-full max-w-md rounded-xl border-3 border-nana-ink bg-nana-cream p-6 shadow-[8px_8px_0_var(--color-nana-ink)]">
                <h3 id="cancel-title" class="font-display text-2xl">Cancelar pedido</h3>
                <div class="mt-4 space-y-2">
                    @foreach (\App\Livewire\Balcao\TodayOrders::CANCEL_REASONS as $reason)
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border-2 border-nana-ink bg-white px-3 text-lg font-semibold">
                            <input type="radio" wire:model.live="cancelReason" value="{{ $reason }}" class="size-5 border-2 border-nana-ink text-nana-red"> {{ $reason }}
                        </label>
                    @endforeach
                    @if ($cancelReason === 'Outro')
                        <input type="text" wire:model="cancelReasonOther" maxlength="100" placeholder="Qual o motivo?"
                               class="block min-h-12 w-full rounded-lg border-2 border-nana-ink bg-white px-4 text-lg">
                    @endif
                    @error('cancelReason') <p class="text-sm font-bold text-nana-red">{{ $message }}</p> @enderror
                    @error('cancelReasonOther') <p class="text-sm font-bold text-nana-red">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-ui.button variant="ghost" wire:click="closeCancel">Voltar</x-ui.button>
                    <x-ui.button wire:click="confirmCancel">Cancelar pedido</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</div>
