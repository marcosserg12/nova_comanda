@props(['variant' => 'primary', 'size' => 'md', 'type' => 'button', 'href' => null])
@php
    $classes = [
        'inline-flex items-center justify-center gap-2 rounded-lg border-2 border-nana-ink leading-none transition active:translate-x-0.5 active:translate-y-0.5 active:shadow-none disabled:opacity-45 disabled:shadow-none',
        match ($variant) {
            'dark' => 'bg-nana-ink text-nana-cream shadow-hard-sm',
            'ghost' => 'bg-white text-nana-ink',
            'success' => 'bg-nana-green text-white shadow-hard-sm',
            default => 'bg-nana-red text-white shadow-hard-sm',
        },
        match ($size) {
            'lg' => 'min-h-14 px-6 font-display text-xl',
            default => 'min-h-11 px-4 text-base font-extrabold max-lg:min-h-12',
        },
    ];
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
