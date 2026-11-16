<x-layouts.app title="Início">
    <div x-data="launcher(@js($allowed), @js($choosing))" class="flex min-h-dvh flex-col">
        <header class="flex items-center gap-4 border-b-3 border-nana-ink bg-nana-red px-4 py-3 text-nana-cream lg:px-8">
            <span class="font-display text-2xl leading-none lg:text-3xl">{{ \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
            @auth
                <div class="ml-auto flex items-center gap-3 font-semibold">
                    <span>Olá, <b class="rounded-md bg-nana-cream px-2 py-1 font-extrabold text-nana-red">{{ auth()->user()->username }}</b></span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="min-h-11 rounded-md border-2 border-nana-cream px-3 font-extrabold">Sair</button>
                    </form>
                </div>
            @endauth
        </header>

        <main x-show="! redirecting" class="flex flex-1 flex-col px-4 py-6 lg:px-10 lg:py-8">
            <h1 class="font-display text-3xl leading-none lg:text-4xl">Onde você quer entrar?</h1>
            <p class="mt-2 text-lg text-nana-muted">Toque em uma tela. Dá para trocar a qualquer momento pelo botão “Telas” no topo.</p>

            <div @class([
                'mt-6 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3',
                'lg:flex-1 lg:auto-rows-fr' => count($screens) > 3,
            ])>
                @foreach ($screens as $screen)
                    @php($main = $screen->key === 'estacao')
                    <a href="{{ $screen->url }}" data-screen="{{ $screen->key }}" @click="choose('{{ $screen->key }}')"
                       @class([
                           'relative flex min-h-44 flex-col justify-end gap-1 rounded-xl border-3 border-nana-ink p-5 shadow-hard transition active:translate-x-0.5 active:translate-y-0.5 active:shadow-none',
                           'bg-nana-red text-nana-cream' => $main,
                           'bg-white text-nana-ink' => ! $main,
                       ])>
                        @if ($screen->badge)
                            <span @class([
                                'absolute right-4 top-4 rounded-md border-2 border-nana-ink px-2 py-1 text-xs font-extrabold uppercase tracking-wide',
                                'bg-nana-ink text-nana-cream' => $screen->key === 'admin',
                                'bg-nana-yellow text-nana-ink' => $screen->key !== 'admin',
                            ])>{{ $screen->badge }}</span>
                        @endif
                        <span @class([
                            'mb-auto grid size-16 place-items-center rounded-lg border-2 border-nana-ink text-nana-ink',
                            'bg-nana-cream' => $main,
                            'bg-white' => $screen->key === 'admin',
                            'bg-nana-yellow' => ! $main && $screen->key !== 'admin',
                        ])>
                            <x-ui.icon :name="$screen->icon" class="size-9" />
                        </span>
                        <span class="mt-5 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="font-display text-3xl leading-tight">{{ $screen->label }}</span>
                            <span x-show="stored === '{{ $screen->key }}'" x-cloak
                                  class="rounded-md border-2 border-nana-ink bg-nana-cream px-2 py-1 text-xs font-extrabold uppercase tracking-wide text-nana-ink">Abre sempre aqui</span>
                        </span>
                        <span @class(['text-lg', 'text-[#FFE3B0]' => $main, 'text-nana-muted' => ! $main])>{{ $screen->description }}</span>
                    </a>
                @endforeach
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-x-6 gap-y-3">
                <label class="flex min-h-11 max-lg:min-h-12 cursor-pointer flex-wrap items-center gap-3 text-lg font-semibold">
                    <input type="checkbox" x-model="remember" class="size-7 rounded border-2 border-nana-ink text-nana-yellow">
                    Abrir sempre esta tela neste aparelho
                    <small class="text-base font-medium text-nana-muted">(dá para desfazer no botão “Telas”)</small>
                </label>
                @if ($choosing)
                    <x-ui.button variant="ghost" x-show="stored" x-cloak @click="forget()">Esquecer a escolha deste aparelho</x-ui.button>
                @endif
            </div>
        </main>
    </div>
</x-layouts.app>
