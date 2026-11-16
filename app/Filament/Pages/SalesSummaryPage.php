<?php

namespace App\Filament\Pages;

use App\Queries\SalesSummary;
use App\Support\BusinessDay;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Computed;

class SalesSummaryPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Resumo de vendas';

    protected static ?string $title = 'Resumo de vendas';

    protected static ?string $slug = 'resumo-de-vendas';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.sales-summary';

    public string $from = '';

    public string $until = '';

    public function mount(): void
    {
        $this->from = BusinessDay::todayString();
        $this->until = BusinessDay::todayString();
    }

    #[Computed]
    public function summary(): array
    {
        $from = CarbonImmutable::parse($this->from !== '' ? $this->from : BusinessDay::todayString());
        $until = CarbonImmutable::parse($this->until !== '' ? $this->until : BusinessDay::todayString());

        if ($until->lt($from)) {
            [$from, $until] = [$until, $from];
        }

        return app(SalesSummary::class)->between($from, $until);
    }
}
