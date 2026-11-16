<div x-data="kitchenBoard" @kitchen-highlight.window="highlight($event.detail)" @kitchen-not-found.window="notFound($event.detail.number)"
     @pointerdown.prevent @mousedown.prevent
     wire:poll.20s class="relative flex h-full min-h-0 flex-col bg-nana-cream text-nana-ink">
    <x-ui.topbar title="Cozinha" current="cozinha">
        <x-slot:badge>
            <span class="rounded-md bg-nana-ink px-2.5 py-1 text-lg font-extrabold leading-none text-nana-cream tabular-nums">{{ $this->orders->count() }}</span>
        </x-slot:badge>
        <x-slot:end>
            <span x-show="typed" x-cloak class="rounded-md border-2 border-nana-ink bg-nana-yellow px-3 py-1 text-xl font-extrabold text-nana-ink" x-text="'Nº ' + typed"></span>
            <span x-data="clock" x-text="time" class="text-xl font-extrabold tabular-nums"></span>
        </x-slot:end>
    </x-ui.topbar>

    <div data-kitchen-grid @scroll.passive="scheduleOverflow()"
         class="grid min-h-0 flex-1 auto-rows-min grid-cols-[repeat(auto-fill,minmax(300px,1fr))] content-start items-start gap-4 overflow-y-auto p-4">
        @forelse ($this->orders as $index => $order)
            @php($minutes = (int) floor(abs($order->created_at->diffInMinutes(now()))))
            @php($state = $minutes >= $lateMinutes ? 'late' : ($minutes >= $warnMinutes ? 'warn' : 'ok'))
            <article wire:key="k-{{ $order->id }}" data-order-id="{{ $order->id }}" data-number="{{ $order->displayNumber() }}" data-edited-at="{{ $order->edited_at?->timestamp }}" data-kitchen-state="{{ $state }}"
                     @pointerdown.prevent @mousedown.prevent @click="acknowledge({{ $order->id }})"
                     :class="{ 'ring-4 ring-nana-ink ring-offset-4 ring-offset-nana-cream': selected === {{ $index }} }"
                     @class([
                         'relative flex flex-col rounded-lg border-2 border-nana-ink shadow-hard',
                         'bg-white' => $state !== 'late',
                         'bg-[#FFE9E9]' => $state === 'late',
                     ])>
                <span x-show="isHighlighted({{ $order->id }})" x-cloak aria-hidden="true" class="nana-highlight pointer-events-none absolute -inset-0.5 rounded-lg"></span>
                <header class="flex items-baseline justify-between gap-2 px-4 pt-3">
                    <span class="flex items-center gap-2">
                        <span class="font-display text-[2.6rem] leading-none tabular-nums">{{ $order->displayNumber() }}</span>
                        <span x-show="highlightKind({{ $order->id }}) === 'created'" x-cloak
                              class="rounded-md border-2 border-nana-ink bg-nana-yellow px-2 py-0.5 text-base font-extrabold leading-tight">NOVO</span>
                        <span x-show="highlightKind({{ $order->id }}) === 'updated'" x-cloak
                              class="rounded-md border-2 border-nana-ink bg-nana-yellow px-2 py-0.5 text-base font-extrabold leading-tight">ALTERADO</span>
                    </span>
                    <span @class([
                        'text-xl font-extrabold tabular-nums',
                        'text-nana-muted' => $state === 'ok',
                        'text-nana-orange' => $state === 'warn',
                        'rounded-md border-2 border-nana-ink bg-nana-red px-2 py-0.5 text-white' => $state === 'late',
                    ])>{{ $minutes === 0 ? 'agora' : $minutes.' min' }}</span>
                </header>
                <p class="truncate px-4 text-2xl font-extrabold leading-tight">{{ $order->customer_name }}</p>
                @if ($order->takeaway || $order->edited_at)
                    <div class="flex flex-wrap gap-2 px-4 pt-1">
                        @if ($order->takeaway)
                            <x-ui.badge tone="outline">VIAGEM</x-ui.badge>
                        @endif
                        @if ($order->edited_at)
                            <x-ui.badge tone="red" x-show="highlightKind({{ $order->id }}) !== 'updated'">ALTERADO</x-ui.badge>
                        @endif
                    </div>
                @endif
                <ul class="space-y-1 px-4 pb-3 pt-2 text-xl font-semibold leading-snug">
                    @foreach ($order->items as $item)
                        <li>
                            <span class="font-extrabold">{{ $item->quantity }}×</span> {{ $item->name_snapshot }}
                            @if ($item->notes)
                                <span class="font-extrabold text-nana-red">{{ $item->notes }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <button type="button" data-ready-button tabindex="-1" @pointerdown.prevent @mousedown.prevent
                        @click.stop="ready({{ $order->id }}, '{{ $order->displayNumber() }}')"
                        class="mt-auto w-full rounded-b-md border-t-2 border-nana-ink bg-nana-green py-3.5 font-display text-2xl tracking-wide text-white active:brightness-90">PRONTO</button>
            </article>
        @empty
            <p class="col-span-full py-24 text-center text-2xl font-bold text-nana-muted">Nenhum pedido na fila.</p>
        @endforelse
    </div>

    <footer data-kitchen-footer :data-kitchen-alert="belowHighlighted > 0 ? '1' : '0'"
            :class="belowHighlighted > 0 ? 'bg-nana-yellow text-nana-ink' : 'bg-nana-ink text-nana-cream'"
            class="relative flex shrink-0 items-center gap-3 px-4 py-2.5 text-base font-bold">
        <button type="button" data-kitchen-alert-bar :data-kitchen-new-below="belowHighlighted" x-show="belowHighlighted > 0" x-cloak tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="scrollToBelow()"
                class="relative flex min-h-12 w-full items-center justify-center text-center font-display text-3xl leading-tight text-nana-ink">
            <span aria-hidden="true" class="nana-highlight pointer-events-none absolute inset-0 rounded-md" style="outline-offset: -6px"></span>
            <span x-text="(belowKind === 'updated' ? 'PEDIDO ALTERADO' : 'NOVO PEDIDO') + ' abaixo ↓ — toque para ver'"></span>
        </button>
        <span x-show="belowHighlighted === 0">Teclado numérico: digite <kbd class="rounded bg-nana-cream px-1.5 py-0.5 font-extrabold text-nana-ink">14</kbd> e <kbd class="rounded bg-nana-cream px-1.5 py-0.5 font-extrabold text-nana-ink">Enter</kbd> para marcar pronto</span>
        <button type="button" data-kitchen-more :data-kitchen-new-below="belowHighlighted" x-show="below > 0 && belowHighlighted === 0" x-cloak tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="scrollToBelow()"
                class="ml-auto rounded-md bg-nana-yellow px-3 py-1 font-extrabold text-nana-ink"
                x-text="'+' + below + (below === 1 ? ' pedido abaixo ↓' : ' pedidos abaixo ↓')"></button>
    </footer>

    <div x-show="notice" x-cloak role="status" data-kitchen-notice
         class="absolute inset-x-4 bottom-16 z-10 rounded-lg border-2 border-nana-ink bg-nana-red px-5 py-3 text-xl font-extrabold text-white shadow-hard">
        <span x-text="notice"></span>
    </div>

    <div x-show="undoBar" x-cloak
         class="absolute inset-x-4 bottom-16 z-10 flex items-center justify-between gap-4 rounded-lg border-2 border-nana-ink bg-nana-green px-5 py-3 text-xl font-extrabold text-white shadow-hard">
        <span x-text="undoBar"></span>
        <button type="button" tabindex="-1" @pointerdown.prevent @mousedown.prevent @click="undo()"
                class="min-h-11 rounded-md border-2 border-nana-ink bg-white px-4 text-nana-ink">Desfazer (−)</button>
    </div>
</div>
