<div x-data="kitchenBoard" @kitchen-beep.window="beep()" @kitchen-not-found.window="notFound($event.detail.number)"
     wire:poll.20s class="relative flex h-full flex-col bg-stone-900 text-white">
    <header class="flex items-center justify-between px-6 py-3">
        <h2 class="text-3xl font-black tracking-wide">
            COZINHA <span class="ml-3 rounded-lg bg-white/10 px-3 py-1 text-2xl">{{ $this->orders->count() }}</span>
        </h2>
        <div class="flex items-center gap-6">
            <span x-show="typed" x-cloak class="rounded-lg bg-nana-yellow px-3 py-1 text-2xl font-black text-nana-ink" x-text="'Nº ' + typed"></span>
            <x-connection-dot />
            <span x-data="clock" x-text="time" class="text-2xl font-bold tabular-nums"></span>
        </div>
    </header>

    <div class="grid min-h-0 flex-1 auto-rows-min grid-cols-2 gap-4 overflow-y-auto p-4 2xl:grid-cols-3">
        @forelse ($this->orders as $index => $order)
            @php($minutes = (int) floor(abs($order->created_at->diffInMinutes(now()))))
            <article wire:key="k-{{ $order->id }}" data-order-id="{{ $order->id }}" data-number="{{ $order->displayNumber() }}"
                     :class="selected === {{ $index }} ? 'ring-8 ring-nana-yellow' : ''"
                     class="flex flex-col overflow-hidden rounded-2xl bg-white text-nana-ink">
                <div @class([
                    'flex items-center justify-between px-4 py-2 text-white',
                    'bg-emerald-600' => $minutes < 10,
                    'bg-amber-500' => $minutes >= 10 && $minutes < 20,
                    'bg-red-600' => $minutes >= 20,
                ])>
                    <span class="text-4xl font-black tabular-nums">{{ $order->displayNumber() }}</span>
                    <span class="text-2xl font-bold">{{ $minutes }} min</span>
                </div>
                <div class="flex-1 px-4 py-3">
                    <p class="truncate text-3xl font-black">{{ $order->customer_name }}</p>
                    <div class="mt-1 flex gap-2">
                        @if ($order->takeaway) <span class="rounded-md bg-nana-yellow px-2 py-1 text-lg font-black">VIAGEM</span> @endif
                        @if ($order->edited_at) <span class="animate-pulse rounded-md bg-red-600 px-2 py-1 text-lg font-black text-white">ALTERADO</span> @endif
                    </div>
                    <ul class="mt-3 space-y-2">
                        @foreach ($order->items as $item)
                            <li class="text-2xl leading-snug">
                                <span class="font-black">{{ $item->quantity }}×</span> {{ $item->name_snapshot }}
                                @if ($item->notes) <span class="block text-xl font-bold uppercase text-red-700">{{ $item->notes }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent
                        @click="ready({{ $order->id }}, '{{ $order->displayNumber() }}')"
                        class="bg-nana-red py-6 text-3xl font-black text-white active:bg-red-800">PRONTO</button>
            </article>
        @empty
            <p class="col-span-full py-24 text-center text-3xl font-bold text-white/40">Nenhum pedido na fila.</p>
        @endforelse
    </div>

    <div x-show="undoBar" x-cloak class="absolute inset-x-4 bottom-4 flex items-center justify-between rounded-2xl bg-emerald-600 px-6 py-4 text-2xl font-black shadow-2xl">
        <span x-text="undoBar"></span>
        <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="undo()"
                class="rounded-xl bg-white/20 px-5 py-2">Desfazer (−)</button>
    </div>
</div>
