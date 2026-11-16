@props(['name'])
@php
    $paths = [
        'station' => '<rect x="2" y="4" width="9" height="12" rx="1"/><rect x="13" y="4" width="9" height="12" rx="1"/><path d="M6 20h12"/>',
        'counter' => '<path d="M4 7h16M4 12h16M4 17h10"/>',
        'kitchen' => '<path d="M6 13h12l-1 7H7z"/><path d="M8 13V9a4 4 0 0 1 8 0v4"/>',
        'tv' => '<rect x="2" y="4" width="20" height="13" rx="1"/><path d="M8 21h8M12 17v4"/>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'admin' => '<path d="M4 21V10M10 21V4M16 21v-7M22 21H2"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
    ];
@endphp
<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" {{ $attributes }}>{!! $paths[$name] ?? '' !!}</svg>
