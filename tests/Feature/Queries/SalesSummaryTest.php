<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Queries\SalesSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

it('sums sales by period excluding canceled orders', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);
    $juice = kitchenProduct(['name' => 'Suco de manga', 'price_cents' => 600]);

    placeOrder([item($dish, 2), item($juice)]);
    placeOrder([item($dish)]);
    $canceled = placeOrder([item($juice, 5)]);
    app(TransitionOrder::class)->cancel($canceled, 'Lançado errado');

    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));
    placeOrder([item($dish)]);

    $day = app(SalesSummary::class)->between(CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-13'));

    expect($day['orders'])->toBe(2)
        ->and($day['total_cents'])->toBe(7800)
        ->and($day['average_cents'])->toBe(3900)
        ->and($day['by_product'])->toBe([
            ['name' => 'Filé de frango', 'quantity' => 3, 'total_cents' => 7200],
            ['name' => 'Suco de manga', 'quantity' => 1, 'total_cents' => 600],
        ]);

    expect(app(SalesSummary::class)->between(CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-14'))['orders'])->toBe(3);
});
