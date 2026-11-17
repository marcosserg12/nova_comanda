<x-filament-panels::page>
    <form wire:submit="save" style="display:grid; gap:1rem; max-width:32rem;">
        <x-filament::section>
            <x-slot name="heading">Painel da TV</x-slot>
            <label style="display:block;">Minutos que o pedido fica em "Prontos"
                <x-filament::input.wrapper><x-filament::input type="number" min="1" max="60" wire:model="readyMinutes" /></x-filament::input.wrapper>
            </label>
            @error('readyMinutes') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
            <label style="display:block; margin-top:1rem;">Nome do restaurante
                <x-filament::input.wrapper><x-filament::input type="text" wire:model="restaurantName" /></x-filament::input.wrapper>
            </label>
            @error('restaurantName') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Balcão + Cozinha (dois monitores)</x-slot>
            <label style="display:block;">Largura do monitor do balcão (pixels)
                <x-filament::input.wrapper><x-filament::input type="number" wire:model="stationLeftWidth" /></x-filament::input.wrapper>
            </label>
            @error('stationLeftWidth') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
            <label style="display:block; margin-top:1rem;">Largura do monitor da cozinha (pixels)
                <x-filament::input.wrapper><x-filament::input type="number" wire:model="stationRightWidth" /></x-filament::input.wrapper>
            </label>
            @error('stationRightWidth') <p style="color:#b91c1c;">{{ $message }}</p> @enderror
        </x-filament::section>

        <div><x-filament::button type="submit">Salvar</x-filament::button></div>
    </form>
</x-filament-panels::page>
