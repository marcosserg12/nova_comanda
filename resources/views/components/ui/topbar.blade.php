@props(['title' => null, 'current' => null, 'screens' => null])
<header {{ $attributes->class('flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b-3 border-nana-ink bg-nana-red px-4 py-2 text-nana-cream') }}>
    <span class="flex items-center gap-2">
        <x-ui.brand-face class="size-10" />
        <span class="font-display text-2xl leading-none">{{ $title ?? \App\Models\Setting::get(\App\Models\Setting::RESTAURANT_NAME) }}</span>
    </span>
    @isset($badge)
        {{ $badge }}
    @endisset
    <x-ui.screens-menu :screens="$screens" :current="$current" />
    @isset($tabs)
        <nav aria-label="Seções" class="flex flex-wrap items-center gap-1">{{ $tabs }}</nav>
    @endisset
    <div class="ml-auto flex items-center gap-4">
        @isset($end)
            {{ $end }}
        @endisset
        <x-connection-dot tone="light" />
    </div>
</header>
