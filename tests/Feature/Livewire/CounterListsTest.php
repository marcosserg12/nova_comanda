<?php

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Livewire\Balcao\InKitchenList;
use App\Livewire\Balcao\TodayOrders;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->user = userWithRole(Role::Balcao);
    $this->dish = kitchenProduct();
});

it('lists orders in the kitchen and marks them ready', function () {
    $order = placeOrder([item($this->dish)], ['customer_name' => 'Carla']);

    Livewire::actingAs($this->user)->test(InKitchenList::class)
        ->assertSee('001')->assertSee('Carla')
        ->call('markReady', $order->id)
        ->assertDispatched('toast')
        ->assertDontSee('Carla');

    expect($order->fresh()->status)->toBe(OrderStatus::Ready);
});

it('shows today orders with totals and searches them', function () {
    placeOrder([item($this->dish)], ['customer_name' => 'Carla']);
    placeOrder([item($this->dish)], ['customer_name' => 'Bruno']);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->assertSee('Carla')->assertSee('Bruno')
        ->set('search', 'brun')
        ->assertSee('Bruno')->assertDontSee('Carla')
        ->set('search', '1')
        ->assertSee('Carla')->assertDontSee('Bruno');
});

it('delivers and sends back to preparing', function () {
    $order = placeOrder([item($this->dish)]);
    app(TransitionOrder::class)->markReady($order);

    Livewire::actingAs($this->user)->test(TodayOrders::class)->call('backToPreparing', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);

    app(TransitionOrder::class)->markReady($order);
    Livewire::actingAs($this->user)->test(TodayOrders::class)->call('markDelivered', $order->id);
    expect($order->fresh()->status)->toBe(OrderStatus::Delivered);
});

it('asks for the edit through an event', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('edit', $order->id)
        ->assertDispatched('edit-order', orderId: $order->id);
});

it('cancels with a chosen reason and requires text for Outro', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->call('confirmCancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Outro')
        ->call('confirmCancel')->assertHasErrors('cancelReasonOther')
        ->set('cancelReasonOther', 'cliente pediu pra trocar')
        ->call('confirmCancel')->assertHasNoErrors()
        ->assertSet('cancelingOrderId', null);

    expect($order->fresh()->status)->toBe(OrderStatus::Canceled)
        ->and($order->fresh()->cancel_reason)->toBe('Outro: cliente pediu pra trocar');
});

it('forbids the kitchen profile from cancelling', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->assertForbidden();
});

it('shows the waiting time for orders in progress', function () {
    placeOrder([item($this->dish)]);

    $this->travel(7)->minutes();

    Livewire::actingAs($this->user)->test(TodayOrders::class)->assertSee('há 7 min');
});

it('forbids the kitchen profile from manager actions but allows markReady', function (string $method) {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(TodayOrders::class)
        ->call($method, $order->id)
        ->assertForbidden();
})->with(['markDelivered', 'backToPreparing', 'edit', 'startCancel']);

it('lets the kitchen profile mark ready', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs(userWithRole(Role::Cozinha))->test(TodayOrders::class)->call('markReady', $order->id);

    expect($order->fresh()->status)->toBe(OrderStatus::Ready);
});

it('stores a preset cancel reason as is', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->set('cancelReason', 'Cliente desistiu')
        ->call('confirmCancel')->assertHasNoErrors();

    expect($order->fresh()->cancel_reason)->toBe('Cliente desistiu');
});

it('rejects an invalid cancel reason', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)
        ->call('startCancel', $order->id)
        ->set('cancelReason', 'Qualquer')
        ->call('confirmCancel')->assertHasErrors('cancelReason');

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});

it('does not let the client set cancelingOrderId', function () {
    Livewire::actingAs($this->user)->test(TodayOrders::class)->set('cancelingOrderId', 1);
})->throws(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);

it('ignores confirmCancel without a selected order', function () {
    $order = placeOrder([item($this->dish)]);

    Livewire::actingAs($this->user)->test(TodayOrders::class)->call('confirmCancel')->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Preparing);
});
