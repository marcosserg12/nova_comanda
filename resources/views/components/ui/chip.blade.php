@props(['active' => false])
<button type="button" aria-pressed="{{ $active ? 'true' : 'false' }}" {{ $attributes->class([
    'inline-flex min-h-11 items-center rounded-full border-2 border-nana-ink px-4 text-base font-extrabold shadow-hard-sm max-lg:min-h-12',
    'bg-nana-yellow' => $active,
    'bg-white' => ! $active,
]) }}>{{ $slot }}</button>
