<x-layouts.app title="Estação" body-class="bg-stone-100 overflow-hidden">
    <x-device-gate />
    <div class="grid h-screen" style="grid-template-columns: {{ $leftWidth }}px {{ $rightWidth }}px;">
        <x-balcao-pane />
        <div class="h-screen border-l-4 border-nana-ink">
            <livewire:cozinha.board />
        </div>
    </div>
</x-layouts.app>
