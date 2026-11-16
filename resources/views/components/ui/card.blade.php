@props(['padding' => 'p-4'])
<div {{ $attributes->class(['rounded-lg border-2 border-nana-ink bg-white shadow-hard', $padding]) }}>{{ $slot }}</div>
