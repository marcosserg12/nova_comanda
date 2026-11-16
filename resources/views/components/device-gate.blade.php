@props(['fullscreen' => false])
<div x-data="deviceGate({{ $fullscreen ? 'true' : 'false' }})" x-show="open" x-cloak
     class="fixed inset-0 z-[60] flex items-center justify-center bg-nana-ink/90 p-6">
    <button type="button" @click="start()"
            class="rounded-2xl border-4 border-nana-ink bg-nana-yellow px-12 py-10 font-display text-4xl text-nana-ink shadow-[8px_8px_0_var(--color-nana-cream)]">
        Toque para começar
    </button>
</div>
