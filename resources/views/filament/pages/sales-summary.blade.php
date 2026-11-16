@use('App\Support\Money')
<x-filament-panels::page>
    <div style="display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end;">
        <label>De
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
        </label>
        <label>Até
            <x-filament::input.wrapper><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
        </label>
    </div>

    @php($summary = $this->summary)

    <div style="display:grid; gap:1rem; grid-template-columns:repeat(3, minmax(0, 1fr));">
        <x-filament::section>
            <x-slot name="heading">Total vendido</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ Money::format($summary['total_cents']) }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Pedidos</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ $summary['orders'] }}</p>
        </x-filament::section>
        <x-filament::section>
            <x-slot name="heading">Ticket médio</x-slot>
            <p style="font-size:1.875rem; font-weight:800;">{{ Money::format($summary['average_cents']) }}</p>
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Por produto</x-slot>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;"><th style="padding:.5rem;">Produto</th><th style="padding:.5rem;">Quantidade</th><th style="padding:.5rem;">Total</th></tr>
            </thead>
            <tbody>
                @forelse ($summary['by_product'] as $row)
                    <tr style="border-top:1px solid rgba(0,0,0,.08);">
                        <td style="padding:.5rem;">{{ $row['name'] }}</td>
                        <td style="padding:.5rem;">{{ $row['quantity'] }}</td>
                        <td style="padding:.5rem;">{{ Money::format($row['total_cents']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="padding:1rem; text-align:center; opacity:.6;">Nenhuma venda no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
