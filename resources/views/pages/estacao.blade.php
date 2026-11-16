<x-layouts.app title="Estação" body-class="bg-nana-cream overflow-hidden">
    <div x-data="station({{ $leftWidth }}, {{ $rightWidth }})" data-configured-left-width="{{ $leftWidth }}"
         class="grid h-dvh" style="grid-template-columns: minmax(0, 1fr)" :style="{ gridTemplateColumns: columns }">
        <div data-station-left class="h-dvh min-w-0">
            <x-balcao-pane current="estacao">
                <x-slot:topbar-end>
                    <span x-show="single" x-cloak class="text-sm font-bold text-[#FFE3B0]">Cozinha no 2º monitor não encontrada</span>
                    <button type="button" @click="detect()" :disabled="detecting"
                            class="min-h-11 rounded-md border-2 border-nana-cream px-3 text-sm font-extrabold text-nana-cream disabled:opacity-45">Detectar monitores</button>
                </x-slot:topbar-end>
            </x-balcao-pane>
        </div>
        <div data-station-right x-show="! single" x-cloak class="h-dvh min-w-0 border-l-4 border-nana-ink">
            <livewire:cozinha.board />
        </div>
    </div>
</x-layouts.app>
