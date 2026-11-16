@props(['tone' => 'yellow'])
<span {{ $attributes->class([
    'inline-flex items-center rounded-md border-2 border-nana-ink px-2 py-0.5 text-sm font-extrabold leading-tight tracking-wide',
    match ($tone) {
        'ink' => 'bg-nana-ink text-nana-cream',
        'red' => 'bg-nana-red text-white',
        'green' => 'bg-nana-green text-white',
        'outline' => 'bg-white text-nana-ink',
        default => 'bg-nana-yellow text-nana-ink',
    },
]) }}>{{ $slot }}</span>
