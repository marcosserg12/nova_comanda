<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Setting;
use App\Queries\BoardQuery;
use App\Queries\KitchenQueue;
use App\Queries\TodayOrdersQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('builds the public board with only number and first name', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)], ['customer_name' => 'Maria Souza']);
    $second = placeOrder([item($dish)], ['customer_name' => 'João']);
    app(TransitionOrder::class)->markReady($second);

    $board = app(BoardQuery::class)->get();

    expect($board['preparing'])->toBe([['id' => $first->id, 'number' => '001', 'first_name' => 'Maria']])
        ->and($board['ready'])->toBe([['id' => $second->id, 'number' => '002', 'first_name' => 'João']]);
});

it('drops ready orders from the board after the configured minutes', function () {
    Setting::put(Setting::READY_MINUTES, 5);
    $order = placeOrder([item(kitchenProduct())]);
    app(TransitionOrder::class)->markReady($order);

    $this->travel(6)->minutes();

    expect(app(BoardQuery::class)->get()['ready'])->toBe([]);
});

it('lists only kitchen items of preparing orders, oldest first', function () {
    $dish = kitchenProduct(['name' => 'Filé de peixe']);
    $soda = cabineProduct(['name' => 'Guaraná']);
    $older = placeOrder([item($dish), item($soda)]);
    $newer = placeOrder([item($dish)]);
    $cabineOnly = placeOrder([item($soda)]);

    $queue = app(KitchenQueue::class)->get();

    expect($queue->pluck('id')->all())->toBe([$older->id, $newer->id])
        ->and($queue->first()->items->pluck('name_snapshot')->all())->toBe(['Filé de peixe'])
        ->and($queue->pluck('id'))->not->toContain($cabineOnly->id);
});

it('searches today orders by number or by name', function () {
    $dish = kitchenProduct();
    $maria = placeOrder([item($dish)], ['customer_name' => 'Maria']);
    $joao = placeOrder([item($dish)], ['customer_name' => 'João Pedro']);

    $query = app(TodayOrdersQuery::class);

    expect($query->search()->pluck('id')->all())->toBe([$joao->id, $maria->id])
        ->and($query->search('1')->pluck('id')->all())->toBe([$maria->id])
        ->and($query->search('pedro')->pluck('id')->all())->toBe([$joao->id]);
});

it('ignores orders from previous days', function () {
    placeOrder([item(kitchenProduct())]);
    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));

    expect(app(KitchenQueue::class)->get())->toHaveCount(0)
        ->and(app(TodayOrdersQuery::class)->search())->toHaveCount(0);
});
