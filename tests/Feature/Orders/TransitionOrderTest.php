<?php

use App\Actions\Orders\TransitionOrder;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $this->actions = app(TransitionOrder::class);
    $this->order = placeOrder([item(kitchenProduct())]);
});

it('marks an order ready once and ignores the second tap', function () {
    $ready = $this->actions->markReady($this->order);
    $again = $this->actions->markReady($this->order);

    expect($ready->status)->toBe(OrderStatus::Ready)
        ->and($ready->ready_at)->not->toBeNull()
        ->and($again->ready_at->equalTo($ready->ready_at))->toBeTrue();
    Event::assertDispatchedTimes(OrdersChanged::class, 2); // created + ready
});

it('lets the kitchen undo within the window but not after it', function () {
    $cozinha = userWithRole(Role::Cozinha);
    $this->actions->markReady($this->order);

    $this->travel(10)->seconds();
    expect($this->actions->undoReady($this->order, $cozinha)->status)->toBe(OrderStatus::Preparing);

    $this->actions->markReady($this->order);
    $this->travel(20)->seconds();
    expect(fn () => $this->actions->undoReady($this->order, $cozinha))->toThrow(AuthorizationException::class);
});

it('lets the counter send a ready order back to preparing at any time', function () {
    $this->actions->markReady($this->order);
    $this->travel(30)->minutes();

    $back = $this->actions->undoReady($this->order, userWithRole(Role::Balcao));

    expect($back->status)->toBe(OrderStatus::Preparing)->and($back->ready_at)->toBeNull();
});

it('delivers only ready orders', function () {
    expect($this->actions->markDelivered($this->order)->status)->toBe(OrderStatus::Preparing);

    $this->actions->markReady($this->order);
    expect($this->actions->markDelivered($this->order)->status)->toBe(OrderStatus::Delivered);
});

it('cancels with a reason and keeps the order in history', function () {
    $canceled = $this->actions->cancel($this->order, 'Cliente desistiu');

    expect($canceled->status)->toBe(OrderStatus::Canceled)
        ->and($canceled->cancel_reason)->toBe('Cliente desistiu')
        ->and($canceled->canceled_at)->not->toBeNull();

    expect($this->actions->markReady($canceled)->status)->toBe(OrderStatus::Canceled);
});

it('requires a cancel reason', function () {
    $this->actions->cancel($this->order, '   ');
})->throws(ValidationException::class);

it('does not broadcast or change anything on no-op transitions', function () {
    $delivered = $this->actions->markDelivered($this->order);
    expect($delivered->status)->toBe(OrderStatus::Preparing);

    $this->actions->cancel($this->order, 'Cliente desistiu');
    Event::fake([OrdersChanged::class, BoardChanged::class]);

    $again = $this->actions->cancel($this->order, 'Outro motivo');

    expect($again->status)->toBe(OrderStatus::Canceled)
        ->and($again->cancel_reason)->toBe('Cliente desistiu');
    Event::assertNotDispatched(OrdersChanged::class);
    Event::assertNotDispatched(BoardChanged::class);
});

it('does not broadcast a delivery attempt on a preparing order', function () {
    $this->actions->markDelivered($this->order);

    Event::assertDispatchedTimes(OrdersChanged::class, 1); // only created
});

it('cancels a ready order and stores the reason', function () {
    $this->actions->markReady($this->order);

    $canceled = $this->actions->cancel($this->order, 'Pedido errado');

    expect($canceled->status)->toBe(OrderStatus::Canceled)
        ->and($canceled->cancel_reason)->toBe('Pedido errado');
});

it('rejects a cancel reason longer than 120 characters', function () {
    try {
        $this->actions->cancel($this->order, str_repeat('a', 121));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('cancelReason');
    }

    expect($this->order->fresh()->status)->toBe(OrderStatus::Preparing);
});
