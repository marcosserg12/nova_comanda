<div x-data="announcer(8000)" @announce.window="enqueue($event.detail)" @announce-cancel.window="cancel($event.detail.orderId)"
     wire:poll.20s class="relative grid h-screen grid-cols-3 overflow-hidden bg-white">
    <section class="col-span-2 p-8">
        <h1 class="text-center text-6xl font-black">Preparando</h1>
        <div class="mt-8 grid grid-cols-3 gap-6">
            @foreach ($this->board['preparing'] as $entry)
                <div wire:key="p-{{ $entry['id'] }}" class="rounded-2xl bg-stone-100 px-4 py-5 text-center">
                    <p class="text-6xl font-black tabular-nums">{{ $entry['number'] }}</p>
                    <p class="mt-1 truncate text-3xl font-bold text-stone-600">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="flex flex-col bg-nana-red p-8 text-white">
        <h1 class="text-center text-6xl font-black">Prontos</h1>
        <div class="mt-8 flex-1 space-y-4 overflow-hidden">
            @foreach ($this->board['ready'] as $entry)
                <div wire:key="r-{{ $entry['id'] }}" class="rounded-2xl bg-white/15 px-4 py-4 text-center">
                    <p class="text-6xl font-black tabular-nums">{{ $entry['number'] }}</p>
                    <p class="truncate text-3xl font-bold">{{ $entry['first_name'] }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex items-end justify-between gap-4">
            <div class="rounded-2xl bg-white p-3 text-center text-nana-ink">
                <img src="{{ $qr }}" alt="QR code para acompanhar o pedido" class="h-36 w-36">
                <p class="mt-1 text-sm font-bold">Acompanhe pelo celular</p>
            </div>
            <span x-data="clock" x-text="time" class="text-5xl font-black tabular-nums"></span>
        </div>
    </section>

    <div x-show="current" x-cloak class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-nana-yellow text-nana-ink">
        <p class="text-6xl font-black uppercase tracking-wide">Pedido pronto</p>
        <p class="mt-6 text-[12rem] font-black leading-none tabular-nums" x-text="current?.number"></p>
        <p class="mt-4 text-8xl font-black" x-text="current?.name"></p>
        <div class="absolute bottom-0 left-0 h-6 bg-nana-red" :style="`width: ${progress}%`"></div>
    </div>
</div>
