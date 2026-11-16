<?php

use App\Actions\Orders\TransitionOrder;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Painel\PublicBoard;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('shows number and first name only, without items or surnames', function () {
    $dish = kitchenProduct(['name' => 'Filé mignon']);
    placeOrder([item($dish)], ['customer_name' => 'Maria Souza']);
    $ready = placeOrder([item($dish)], ['customer_name' => 'João Silva']);
    app(TransitionOrder::class)->markReady($ready);

    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSee('Preparando')->assertSee('Prontos')
        ->assertSee('001')->assertSee('Maria')
        ->assertSee('002')->assertSee('João')
        ->assertDontSee('Souza')->assertDontSee('Silva')->assertDontSee('Filé mignon')
        ->assertSee('data:image/svg+xml;base64,', false);
});

it('announces ready orders and cancels undone announcements', function () {
    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->call('onBoardChanged', ['orderId' => 7, 'kind' => 'ready', 'number' => '007', 'firstName' => 'Ana', 'status' => 'ready'])
        ->assertDispatched('announce', orderId: 7, number: '007', name: 'Ana')
        ->call('onBoardChanged', ['orderId' => 7, 'kind' => 'unready', 'number' => '007', 'firstName' => 'Ana', 'status' => 'preparing'])
        ->assertDispatched('announce-cancel', orderId: 7);
});

it('renders the phone layout', function () {
    placeOrder([item(kitchenProduct())], ['customer_name' => 'Paula']);

    Livewire::test(PublicBoard::class, ['variant' => 'mobile'])
        ->assertSee('Seu número')->assertSee('001')->assertSee('Paula');
});

it('serves both public pages to guests', function () {
    $this->get('/painel')->assertOk()->assertSeeLivewire(PublicBoard::class);
    $this->get('/acompanhar')->assertOk()->assertSeeLivewire(PublicBoard::class);
});
