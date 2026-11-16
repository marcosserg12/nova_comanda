<div x-data="trackMine" @announce.window="onAnnounce($event.detail)" wire:poll.20s class="mx-auto flex min-h-dvh max-w-md flex-col bg-nana-cream">
    @php($readyNumbers = array_column($this->board['ready'], 'number'))
    @php($preparingNumbers = array_column($this->board['preparing'], 'number'))
    <span hidden wire:key="sync-{{ md5(json_encode([$readyNumbers, $preparingNumbers])) }}" x-init="sync(@js($readyNumbers), @js($preparingNumbers))"></span>

    <header class="relative border-b-4 border-nana-ink bg-nana-red px-4 pb-4 pt-5 text-center text-nana-cream">
        <h1 class="font-display text-3xl leading-none">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</h1>
        <p class="mt-1.5 text-sm font-bold text-[#FFE3B0]">Acompanhe seu pedido</p>
        <div class="absolute right-3 top-3">
            <x-ui.screens-menu compact align="right" current="acompanhar" />
        </div>
    </header>

    <main class="flex flex-col gap-4 p-5">
        <label class="flex items-center justify-between gap-3 rounded-xl border-3 border-nana-ink bg-white px-4 py-2">
            <input type="tel" inputmode="numeric" maxlength="3" x-model="mine" placeholder="Ex.: 42"
                   class="min-w-0 flex-1 border-0 p-0 font-display text-3xl placeholder:text-nana-line">
            <span class="shrink-0 text-xs font-extrabold uppercase tracking-wider text-nana-muted">Seu número</span>
        </label>

        <div x-show="myState === 'empty'" class="rounded-xl border-3 border-dashed border-nana-muted p-5 text-center text-lg font-bold text-nana-muted">
            Digite o número do seu pedido para acompanhar.
        </div>
        <div data-my-order="preparing" x-show="myState === 'preparing'" x-cloak class="rounded-xl border-3 border-nana-ink bg-white p-5 text-center shadow-hard">
            <p class="text-base font-extrabold text-nana-muted">Pedido</p>
            <p class="my-2 font-display text-7xl leading-none tabular-nums" x-text="myNumber"></p>
            <span class="inline-block rounded-lg border-2 border-nana-ink bg-nana-cream px-4 py-2 text-lg font-extrabold">Preparando…</span>
        </div>
        <div data-my-order="ready" x-show="myState === 'ready'" x-cloak class="rounded-xl border-3 border-nana-ink bg-nana-yellow p-5 text-center shadow-hard">
            <p class="text-base font-extrabold">Seu pedido está</p>
            <p class="my-2 font-display text-5xl leading-none">PRONTO!</p>
            <span class="inline-block rounded-lg border-2 border-nana-ink bg-nana-ink px-4 py-2 text-lg font-extrabold text-nana-cream">Retire no balcão</span>
        </div>
        <div x-show="myState === 'unknown'" x-cloak class="rounded-xl border-3 border-nana-ink bg-white p-5 text-center text-lg font-bold">
            Não encontramos o pedido <span class="font-display" x-text="myNumber"></span> na fila agora.
        </div>

        <section>
            <h2 class="font-display text-xl">Prontos</h2>
            <ul class="mt-2 space-y-1 text-lg font-bold text-nana-muted">
                @forelse ($this->board['ready'] as $entry)
                    <li wire:key="mr-{{ $entry['id'] }}" class="rounded-md px-2 py-1" :class="isMine('{{ $entry['number'] }}') ? 'bg-nana-yellow text-nana-ink' : ''">
                        <span class="mr-2 font-display text-xl text-nana-ink tabular-nums">{{ $entry['number'] }}</span>{{ $entry['first_name'] }} — pronto
                    </li>
                @empty
                    <li class="px-2 py-1">Nenhum pedido pronto agora.</li>
                @endforelse
            </ul>
        </section>

        <section>
            <h2 class="font-display text-xl">Preparando</h2>
            <ul class="mt-2 space-y-1 text-lg font-bold text-nana-muted">
                @forelse ($this->board['preparing'] as $entry)
                    <li wire:key="mp-{{ $entry['id'] }}" data-phone-preparing="{{ $entry['number'] }}" class="rounded-md px-2 py-1" :class="isMine('{{ $entry['number'] }}') ? 'bg-white text-nana-ink' : ''">
                        <span class="mr-2 font-display text-xl text-nana-ink tabular-nums">{{ $entry['number'] }}</span>{{ $entry['first_name'] }} — preparando
                    </li>
                @empty
                    <li class="px-2 py-1">Nenhum pedido em preparo.</li>
                @endforelse
            </ul>
        </section>
    </main>
</div>
