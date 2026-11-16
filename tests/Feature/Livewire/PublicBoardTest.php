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
        ->assertSee('Preparando')->assertSee('Pronto!')
        ->assertSee('001')->assertSee('Maria')
        ->assertSee('002')->assertSee('João')
        ->assertDontSee('Souza')->assertDontSee('Silva')->assertDontSee('Filé mignon')
        ->assertSee('data:image/svg+xml;base64,', false);
});

it('shows at most 12 preparing orders on the TV, oldest first, and counts the rest', function () {
    $dish = kitchenProduct();
    foreach (range(1, 14) as $index) {
        placeOrder([item($dish)], ['customer_name' => "Cliente{$index}"]);
    }

    $html = Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSeeHtml('data-tv-preparing="001"')
        ->assertSeeHtml('data-tv-preparing="012"')
        ->assertDontSeeHtml('data-tv-preparing="013"')
        ->assertSeeHtml('data-tv-overflow')
        ->assertSee('+2')
        ->html();

    expect(substr_count($html, 'data-tv-preparing="'))->toBe(12);
});

it('lists ready orders newest first and keeps the announcement copy', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)], ['customer_name' => 'Ana']);
    $second = placeOrder([item($dish)], ['customer_name' => 'Bia']);
    app(TransitionOrder::class)->markReady($first);
    $this->travel(1)->minutes();
    app(TransitionOrder::class)->markReady($second);

    Livewire::test(PublicBoard::class, ['variant' => 'tv'])
        ->assertSeeHtmlInOrder(['data-tv-ready="002"', 'data-tv-ready="001"'])
        ->assertSee('PEDIDO PRONTO')
        ->assertSee('Retire no balcão')
        ->assertSee('Acompanhe pelo celular')
        ->assertDontSeeHtml('data-tv-overflow');
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

it('renders the phone tracker with both states of my order and short lists only', function () {
    $dish = kitchenProduct(['name' => 'Filé mignon']);
    placeOrder([item($dish)], ['customer_name' => 'Paula Souza']);
    $ready = placeOrder([item($dish)], ['customer_name' => 'Rui Lima']);
    app(TransitionOrder::class)->markReady($ready);

    Livewire::test(PublicBoard::class, ['variant' => 'mobile'])
        ->assertSee('Acompanhe seu pedido')
        ->assertSee('Preparando…')
        ->assertSee('PRONTO!')
        ->assertSee('Retire no balcão')
        ->assertSeeHtml('x-init="sync(')
        ->assertSee('Paula')->assertSee('Rui')
        ->assertDontSee('Souza')->assertDontSee('Lima')->assertDontSee('Filé mignon');
});

it('serves both public pages to guests', function () {
    $this->get('/painel')->assertOk()->assertSeeLivewire(PublicBoard::class)->assertSee('Toque para começar');
    $this->get('/acompanhar')->assertOk()->assertSeeLivewire(PublicBoard::class)->assertDontSee('Toque para começar');
});

it('caps the TV ready list at the limit', function () {
    $dish = kitchenProduct();
    foreach (range(1, 6) as $index) {
        app(TransitionOrder::class)->markReady(placeOrder([item($dish)], ['customer_name' => "Cliente{$index}"]));
    }

    $html = Livewire::test(PublicBoard::class, ['variant' => 'tv'])->html();

    expect(substr_count($html, 'data-tv-ready="'))->toBe(PublicBoard::TV_READY_LIMIT);
});

it('caps the phone lists and counts the rest', function () {
    $dish = kitchenProduct();
    foreach (range(1, 9) as $index) {
        $order = placeOrder([item($dish)], ['customer_name' => "Pronto{$index}"]);
        app(TransitionOrder::class)->markReady($order);
    }
    foreach (range(1, 8) as $index) {
        placeOrder([item($dish)], ['customer_name' => "Fila{$index}"]);
    }

    $html = Livewire::test(PublicBoard::class, ['variant' => 'mobile'])
        ->assertSeeHtml('data-phone-overflow="ready"')
        ->assertSeeHtml('data-phone-overflow="preparing"')
        ->assertSeeHtml('>+3<')
        ->assertSeeHtml('>+2<')
        ->assertSeeHtml('inputmode="numeric" pattern="[0-9]*"')
        ->html();

    expect(substr_count($html, 'data-phone-preparing="'))->toBe(PublicBoard::PHONE_LIST_LIMIT);
});
