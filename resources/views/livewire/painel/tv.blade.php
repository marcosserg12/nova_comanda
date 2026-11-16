<div x-data="announcer(8000)" @announce.window="enqueue($event.detail)" @announce-cancel.window="cancel($event.detail.orderId)"
     wire:poll.20s class="relative grid h-dvh grid-cols-[minmax(0,1fr)_36vw] overflow-hidden bg-nana-cream text-nana-ink">
    <section class="flex min-h-0 flex-col p-[2.3vw]">
        <h1 class="flex items-center gap-[1.2vw] font-display text-[3.6vw] leading-none">
            Preparando
            <span class="rounded-[0.6vw] bg-nana-ink px-[1vw] py-[0.5vw] font-sans text-[1.8vw] font-extrabold text-nana-cream">{{ count($this->board['preparing']) }}</span>
        </h1>
        <div class="mt-[1.6vw] grid grid-cols-4 gap-[1.2vw]">
            @foreach ($this->tvPreparing as $entry)
                <div wire:key="p-{{ $entry['id'] }}" data-tv-preparing="{{ $entry['number'] }}"
                     class="rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-white px-[0.6vw] py-[1.1vw] text-center shadow-[0.35vw_0.35vw_0_var(--color-nana-ink)]">
                    <p class="font-display text-[4.2vw] leading-none tabular-nums">{{ $entry['number'] }}</p>
                    <p class="mt-[0.3vw] truncate text-[1.7vw] font-bold text-nana-muted">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
            @if ($this->tvPreparingOverflow > 0)
                <div data-tv-overflow class="grid place-items-center rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-nana-ink px-[0.6vw] py-[1.1vw] text-nana-cream">
                    <p class="font-display text-[4.2vw] leading-none">+{{ $this->tvPreparingOverflow }}</p>
                </div>
            @endif
        </div>
        <div class="mt-auto flex items-end justify-between gap-[1vw] pt-[1vw]">
            <span class="font-display text-[2vw] leading-none text-nana-red">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
            <div class="opacity-40 transition hover:opacity-100 focus-within:opacity-100">
                <x-ui.screens-menu compact up current="painel" />
            </div>
        </div>
    </section>

    <section class="flex min-h-0 flex-col border-l-[0.4vw] border-nana-ink bg-nana-red p-[2.3vw] text-nana-cream">
        <h1 class="flex items-center gap-[1.2vw] font-display text-[3.6vw] leading-none">
            Pronto!
            <span class="rounded-[0.6vw] bg-nana-cream px-[1vw] py-[0.5vw] font-sans text-[1.8vw] font-extrabold text-nana-red">{{ count($this->board['ready']) }}</span>
        </h1>
        <div class="mt-[1.6vw] flex min-h-0 flex-1 flex-col gap-[1.2vw] overflow-hidden">
            @foreach ($this->tvReady as $entry)
                <div wire:key="r-{{ $entry['id'] }}" data-tv-ready="{{ $entry['number'] }}"
                     class="flex items-center justify-between gap-[1vw] rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-nana-cream px-[1.8vw] py-[0.8vw] text-nana-ink shadow-[0.35vw_0.35vw_0_var(--color-nana-ink)]">
                    <span class="font-display text-[4.2vw] leading-none tabular-nums">{{ $entry['number'] }}</span>
                    <span class="truncate text-[2.4vw] font-extrabold">{{ $entry['first_name'] }}</span>
                </div>
            @endforeach
        </div>
        <div class="mt-[1.2vw] flex items-end justify-between gap-[1vw]">
            <div class="w-[12vw] rounded-[0.8vw] border-[0.25vw] border-nana-ink bg-white p-[0.9vw] text-center text-nana-ink">
                <img src="{{ $qr }}" alt="QR code para acompanhar o pedido" class="aspect-square w-full">
                <p class="mt-[0.6vw] text-[1.1vw] font-extrabold leading-tight">Acompanhe pelo celular</p>
            </div>
            <span x-data="clock" x-text="time" class="font-display text-[3.6vw] leading-none tabular-nums"></span>
        </div>
    </section>

    <div x-show="current" x-cloak class="absolute inset-0 z-30 flex flex-col items-center justify-center border-[1.4vw] border-nana-ink bg-nana-yellow text-nana-ink">
        <p class="font-display text-[5vw] leading-none">PEDIDO PRONTO</p>
        <p class="mt-[1vw] font-display text-[17vw] leading-none tabular-nums" x-text="current?.number"></p>
        <p class="text-[6vw] font-extrabold leading-none" x-text="current?.name"></p>
        <p class="mt-[1.5vw] rounded-[0.8vw] bg-nana-ink px-[1.6vw] py-[0.8vw] text-[2.4vw] font-bold text-nana-cream">Retire no balcão</p>
        <div class="absolute bottom-0 left-0 h-[1.2vw] bg-nana-ink" :style="`width: ${progress}%`"></div>
    </div>
</div>
