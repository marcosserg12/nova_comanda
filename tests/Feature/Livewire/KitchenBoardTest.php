<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Cozinha\Board;
use App\Actions\Orders\UpdateOrderItems;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->cozinha = userWithRole(Role::Cozinha);
});

it('shows only kitchen items with notes, takeaway and edited flags', function () {
    $dish = kitchenProduct(['name' => 'Filé de peixe']);
    $soda = cabineProduct(['name' => 'Guaraná lata']);
    $order = placeOrder([item($dish, 2, 'Sem arroz'), item($soda)], ['customer_name' => 'Rita', 'takeaway' => true]);
    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'Rita', 'takeaway' => true, 'items' => [item($dish, 2, 'Sem arroz'), item($soda)]]);

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->assertSee('001')->assertSee('Rita')
        ->assertSee('Filé de peixe')->assertSee('Sem arroz')
        ->assertSee('VIAGEM')->assertSee('ALTERADO')
        ->assertDontSee('Guaraná lata');
});

it('marks ready by id and by typed number', function () {
    $dish = kitchenProduct();
    $first = placeOrder([item($dish)]);
    $second = placeOrder([item($dish)]);

    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $first->id);
    expect($first->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 2)->assertReturned($second->id);
    expect($second->fresh()->status)->toBe(OrderStatus::Ready);

    $component->call('markReadyByNumber', 99)->assertReturned(null)->assertDispatched('kitchen-not-found', number: '099');
});

it('lets the kitchen undo only inside the window', function () {
    $order = placeOrder([item(kitchenProduct())]);
    $component = Livewire::actingAs($this->cozinha)->test(Board::class)->call('markReady', $order->id);

    $component->call('undoReady', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    $component->call('markReady', $order->id);
    $this->travel(30)->seconds();
    $component->call('undoReady', $order->id)->assertForbidden();
});

it('beeps for new and edited orders only', function () {
    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 1, 'kind' => 'created'])->assertDispatched('kitchen-beep');

    Livewire::actingAs($this->cozinha)->test(Board::class)
        ->call('onOrdersChanged', ['orderId' => 1, 'kind' => 'ready'])->assertNotDispatched('kitchen-beep');
});

it('refuses guests', function () {
    $order = placeOrder([item(kitchenProduct())]);

    Livewire::test(Board::class)->call('markReady', $order->id)->assertForbidden();
});
