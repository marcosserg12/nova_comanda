@props(['screens' => null, 'current' => null, 'compact' => false, 'up' => false, 'align' => 'left'])
@php
    $screens ??= \App\Support\Launchpad::for(auth()->user());
    $itemClass = 'flex min-h-12 w-full items-center px-4 text-left font-bold hover:bg-nana-yellow focus-visible:bg-nana-yellow';
@endphp
<div x-data="{ open: false }" @keydown.escape="open = false; $refs.toggle.focus()" @click.outside="open = false" class="relative">
    <button type="button" x-ref="toggle" @click="open = ! open" aria-haspopup="menu" :aria-expanded="open.toString()" aria-label="Telas"
            @keydown.arrow-down.prevent="open = true; $nextTick(() => $focus.within($refs.items).first())"
            class="inline-flex min-h-11 items-center gap-2 rounded-md border-2 border-nana-ink bg-nana-cream px-3 font-extrabold text-nana-ink shadow-hard-sm max-lg:min-h-12">
        <x-ui.icon name="menu" class="size-5" />
        @unless ($compact)
            <span>Telas</span>
        @endunless
    </button>
    <div x-ref="items" x-show="open" x-cloak role="menu" aria-label="Telas"
         @keydown.arrow-down.prevent="$focus.within($refs.items).wrap().next()"
         @keydown.arrow-up.prevent="$focus.within($refs.items).wrap().previous()"
         @class([
             'absolute z-50 w-72 overflow-hidden rounded-lg border-2 border-nana-ink bg-white text-nana-ink shadow-hard',
             'top-full mt-2' => ! $up,
             'bottom-full mb-2' => $up,
             'left-0' => $align === 'left',
             'right-0' => $align === 'right',
         ])>
        @foreach ($screens as $screen)
            <a href="{{ $screen->url }}" role="menuitem" data-screen-link="{{ $screen->key }}"
               @if ($screen->key === $current) aria-current="page" @endif
               @class([$itemClass, 'border-b-2 border-nana-line', 'bg-nana-yellow' => $screen->key === $current])>{{ $screen->label }}</a>
        @endforeach
        <a href="{{ route('home', ['escolher' => 1]) }}" role="menuitem" data-screen-link="inicio" class="{{ $itemClass }}">Tela de início</a>
        @auth
            <form method="POST" action="{{ route('logout') }}" class="border-t-2 border-nana-ink">
                @csrf
                <button type="submit" role="menuitem" class="{{ $itemClass }} text-nana-red">Sair</button>
            </form>
        @endauth
    </div>
</div>
