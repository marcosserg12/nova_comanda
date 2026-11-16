<div x-data="trackMine" @announce.window="onAnnounce($event.detail)" wire:poll.20s class="mx-auto min-h-screen max-w-md bg-white p-4">
    <h1 class="text-center text-3xl font-black text-nana-red">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</h1>

    <label class="mt-4 block">
        <span class="text-sm font-semibold uppercase text-stone-500">Seu número</span>
        <input type="tel" inputmode="numeric" maxlength="3" x-model="mine" placeholder="Ex.: 42"
               class="mt-1 w-full rounded-xl border border-stone-300 px-4 py-3 text-2xl font-black">
    </label>

    <p x-show="alert" x-cloak class="mt-4 rounded-2xl bg-nana-yellow p-4 text-center text-2xl font-black">Seu pedido está pronto!</p>

    <h2 class="mt-6 rounded-t-2xl bg-nana-red px-4 py-2 text-xl font-black text-white">Prontos</h2>
    <ul class="divide-y divide-stone-100 rounded-b-2xl border border-stone-200">
        @forelse ($this->board['ready'] as $entry)
            <li wire:key="mr-{{ $entry['id'] }}" class="flex items-center justify-between px-4 py-3"
                :class="isMine('{{ $entry['number'] }}') ? 'bg-nana-yellow' : ''">
                <span class="text-3xl font-black tabular-nums">{{ $entry['number'] }}</span>
                <span class="text-xl font-bold">{{ $entry['first_name'] }}</span>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-stone-400">Nenhum pedido pronto agora.</li>
        @endforelse
    </ul>

    <h2 class="mt-6 rounded-t-2xl bg-stone-800 px-4 py-2 text-xl font-black text-white">Preparando</h2>
    <ul class="divide-y divide-stone-100 rounded-b-2xl border border-stone-200">
        @forelse ($this->board['preparing'] as $entry)
            <li wire:key="mp-{{ $entry['id'] }}" class="flex items-center justify-between px-4 py-3"
                :class="isMine('{{ $entry['number'] }}') ? 'bg-amber-100' : ''">
                <span class="text-3xl font-black tabular-nums">{{ $entry['number'] }}</span>
                <span class="text-xl font-bold">{{ $entry['first_name'] }}</span>
            </li>
        @empty
            <li class="px-4 py-6 text-center text-stone-400">Nenhum pedido em preparo.</li>
        @endforelse
    </ul>
</div>
