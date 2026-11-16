@props(['tone' => 'dark'])
@php($onColor = $tone === 'light' ? 'bg-[#7CFFB2]' : 'bg-nana-green')
<span x-data {{ $attributes->class(['inline-flex items-center gap-2 text-sm font-bold', 'text-[#FFE3B0]' => $tone === 'light']) }}>
    <span class="size-3 rounded-full" :class="$store.conn.online ? '{{ $onColor }}' : 'animate-pulse bg-nana-yellow'"></span>
    <span x-text="$store.conn.online ? 'Online' : 'Sem conexão'">Online</span>
</span>
