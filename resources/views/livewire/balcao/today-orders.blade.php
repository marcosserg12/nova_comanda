@use('App\Enums\OrderStatus')
@use('App\Support\Money')
<div wire:poll.20s class="flex h-full min-h-0 flex-col gap-3">
    <input type="search" wire:model.live.debounce.200ms="search" placeholder="Buscar por número ou nome…"
           class="w-full rounded-xl border border-stone-300 px-4 py-3 text-lg">

    <div class="min-h-0 flex-1 overflow-y-auto rounded-2xl bg-white shadow">
        <table class="w-full text-left">
            <thead class="sticky top-0 bg-stone-50 text-sm uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nº</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Hora</th><th class="px-4 py-3 text-right">Total</th><th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->orders as $order)
                    <tr wire:key="today-{{ $order->id }}" class="border-t border-stone-100 text-lg">
                        <td class="px-4 py-3 font-black tabular-nums">{{ $order->displayNumber() }}</td>
                        <td class="px-4 py-3 font-semibold">
                            {{ $order->customer_name }}
                            @if ($order->takeaway) <span class="ml-1 rounded bg-nana-yellow px-2 text-sm font-bold">VIAGEM</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-3 py-1 text-sm font-bold',
                                'bg-amber-100 text-amber-800' => $order->status === OrderStatus::Preparing,
                                'bg-emerald-100 text-emerald-800' => $order->status === OrderStatus::Ready,
                                'bg-stone-100 text-stone-600' => $order->status === OrderStatus::Delivered,
                                'bg-red-100 text-red-700' => $order->status === OrderStatus::Canceled,
                            ])>{{ $order->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 tabular-nums text-stone-500">{{ $order->created_at->format('H:i') }}</td>
                        <td class="px-4 py-3 text-right font-black tabular-nums">{{ Money::format($order->total_cents) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                @if ($order->status === OrderStatus::Preparing)
                                    <button type="button" wire:click="markReady({{ $order->id }})" class="rounded-lg bg-emerald-600 px-3 py-2 font-bold text-white">Pronto</button>
                                    <button type="button" wire:click="edit({{ $order->id }})" class="rounded-lg bg-stone-200 px-3 py-2 font-bold">Editar</button>
                                @elseif ($order->status === OrderStatus::Ready)
                                    <button type="button" wire:click="markDelivered({{ $order->id }})" class="rounded-lg bg-nana-ink px-3 py-2 font-bold text-white">Entregue</button>
                                    <button type="button" wire:click="backToPreparing({{ $order->id }})" class="rounded-lg bg-stone-200 px-3 py-2 font-bold">Voltar p/ preparo</button>
                                @endif
                                @if (in_array($order->status, [OrderStatus::Preparing, OrderStatus::Ready], true))
                                    <button type="button" wire:click="startCancel({{ $order->id }})" class="rounded-lg px-3 py-2 font-bold text-red-700">Cancelar</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-stone-400">Nenhum pedido hoje.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($cancelingOrderId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50">
            <div class="w-[28rem] rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-2xl font-black">Cancelar pedido</h3>
                <div class="mt-4 space-y-2">
                    @foreach (\App\Livewire\Balcao\TodayOrders::CANCEL_REASONS as $reason)
                        <label class="flex items-center gap-3 text-lg">
                            <input type="radio" wire:model.live="cancelReason" value="{{ $reason }}" class="h-5 w-5"> {{ $reason }}
                        </label>
                    @endforeach
                    @if ($cancelReason === 'Outro')
                        <input type="text" wire:model="cancelReasonOther" maxlength="100" placeholder="Qual o motivo?"
                               class="w-full rounded-xl border border-stone-300 px-4 py-3">
                    @endif
                    @error('cancelReason') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                    @error('cancelReasonOther') <p class="text-sm font-semibold text-red-700">{{ $message }}</p> @enderror
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" wire:click="closeCancel" class="rounded-xl px-4 py-3 font-semibold">Voltar</button>
                    <button type="button" wire:click="confirmCancel" class="rounded-xl bg-red-700 px-6 py-3 font-bold text-white">Cancelar pedido</button>
                </div>
            </div>
        </div>
    @endif
</div>
