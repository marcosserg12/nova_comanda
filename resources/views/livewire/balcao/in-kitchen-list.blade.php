<div wire:poll.20s class="flex h-full min-h-0 flex-col rounded-lg border-2 border-nana-ink bg-white p-3 shadow-hard">
    <span hidden x-data wire:key="ik-count-{{ $this->orders->count() }}" data-in-kitchen-count="{{ $this->orders->count() }}"
          x-init="$dispatch('in-kitchen-count', { count: {{ $this->orders->count() }} })"></span>
    <h3 class="mb-2 font-display text-lg leading-none">Na cozinha · {{ $this->orders->count() }}</h3>
    <ul class="min-h-0 flex-1 space-y-2 overflow-y-auto">
        @forelse ($this->orders as $order)
            <li wire:key="ik-{{ $order->id }}" data-in-kitchen-order="{{ $order->id }}" class="flex items-center gap-2 rounded-md border-2 border-nana-line px-2 py-1.5">
                <div class="min-w-0 flex-1">
                    <p class="font-display text-xl leading-none tabular-nums">{{ $order->displayNumber() }}</p>
                    <p class="truncate text-sm font-semibold text-nana-muted">{{ $order->customer_name }}</p>
                </div>
                <button type="button" wire:click="markReady({{ $order->id }})"
                        class="min-h-11 max-lg:min-h-12 shrink-0 rounded-md border-2 border-nana-ink bg-nana-green px-3 text-sm font-extrabold text-white">Pronto</button>
            </li>
        @empty
            <li class="py-8 text-center font-semibold text-nana-muted">Nada na cozinha.</li>
        @endforelse
    </ul>
</div>
