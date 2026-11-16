@props(['fullscreen' => false])
<div x-data="deviceGate({{ $fullscreen ? 'true' : 'false' }})" x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center bg-nana-ink/90">
    <button type="button" @click="start()"
            class="rounded-3xl bg-nana-yellow px-12 py-10 text-4xl font-black text-nana-ink shadow-2xl">
        Toque para começar
    </button>
</div>
