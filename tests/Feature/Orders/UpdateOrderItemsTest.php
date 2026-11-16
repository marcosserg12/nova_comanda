<?php

use App\Actions\Orders\TransitionOrder;
use App\Actions\Orders\UpdateOrderItems;
use App\Enums\OrderStatus;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('replaces items, recomputes the total and flags the order as edited', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $juice = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish)]);

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria Clara',
        'takeaway' => true,
        'items' => [item($dish, 1, 'Sem salada'), item($juice, 2)],
    ]);

    expect($updated->items)->toHaveCount(2)
        ->and($updated->total_cents)->toBe(3400)
        ->and($updated->customer_name)->toBe('Maria Clara')
        ->and($updated->edited_at)->not->toBeNull()
        ->and($updated->number)->toBe($order->number);
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'updated');
});

it('refuses to edit an order that is no longer preparing', function () {
    $dish = kitchenProduct();
    $order = placeOrder([item($dish)]);
    app(TransitionOrder::class)->markReady($order);

    app(UpdateOrderItems::class)->handle($order, ['customer_name' => 'X', 'takeaway' => false, 'items' => [item($dish)]]);
})->throws(ValidationException::class);

it('delivers the order when no kitchen item is left', function () {
    $order = placeOrder([item(kitchenProduct())]);

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => false, 'items' => [item(cabineProduct())],
    ]);

    expect($updated->status)->toBe(OrderStatus::Delivered);
});

it('leaves items and total untouched when the edit is refused', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $other = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish)]);
    app(TransitionOrder::class)->markReady($order);
    $names = $order->fresh(['items'])->items->pluck('product_name')->all();

    try {
        app(UpdateOrderItems::class)->handle($order, [
            'customer_name' => 'X', 'takeaway' => false, 'items' => [item($other, 3)],
        ]);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('order');
    }

    $fresh = $order->fresh(['items']);
    expect($fresh->items)->toHaveCount(1)
        ->and($fresh->items->pluck('product_name')->all())->toBe($names)
        ->and($fresh->total_cents)->toBe(2400)
        ->and($fresh->edited_at)->toBeNull();
});
