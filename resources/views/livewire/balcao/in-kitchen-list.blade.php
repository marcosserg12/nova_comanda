<div wire:poll.20s class="flex h-full flex-col rounded-2xl bg-white p-3 shadow">
    <h3 class="mb-2 text-lg font-black uppercase tracking-wide text-stone-500">Na cozinha ({{ $this->orders->count() }})</h3>
    <ul class="min-h-0 flex-1 space-y-2 overflow-y-auto">
        @forelse ($this->orders as $order)
            <li wire:key="ik-{{ $order->id }}" class="flex items-center gap-2 rounded-xl bg-stone-50 p-2">
                <div class="min-w-0 flex-1">
                    <p class="text-xl font-black tabular-nums">{{ $order->displayNumber() }}</p>
                    <p class="truncate font-semibold text-stone-600">{{ $order->customer_name }}</p>
                </div>
                <button type="button" wire:click="markReady({{ $order->id }})"
                        class="rounded-xl bg-emerald-600 px-4 py-3 text-lg font-black text-white">Pronto</button>
            </li>
        @empty
            <li class="py-8 text-center text-stone-400">Nada na cozinha.</li>
        @endforelse
    </ul>
</div>
