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
    $before = $order->fresh(['items'])->items->map->only(['product_id', 'name_snapshot', 'quantity'])->all();

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
        ->and($fresh->items->map->only(['product_id', 'name_snapshot', 'quantity'])->all())->toBe($before)
        ->and($fresh->total_cents)->toBe(2400)
        ->and($fresh->edited_at)->toBeNull();
});

it('keeps existing lines when the dish sold out after ordering and lets a new item be added', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $juice = kitchenProduct(['price_cents' => 500]);
    $order = placeOrder([item($dish)]);
    $dish->toggleSoldOut();

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => false, 'items' => [item($dish), item($juice)],
    ]);

    expect($updated->items->pluck('product_id')->all())->toBe([$dish->id, $juice->id])
        ->and($updated->total_cents)->toBe(2900);
});

it('keeps the ordered price when the product price changed', function () {
    $dish = kitchenProduct(['price_cents' => 2400]);
    $order = placeOrder([item($dish, 2)]);
    $dish->update(['price_cents' => 3000]);

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => true, 'items' => [item($dish, 2)],
    ]);

    expect($updated->total_cents)->toBe(4800)
        ->and($updated->items->sole()->unit_price_cents)->toBe(2400);
});

it('keeps the ordered dish-of-the-day name after it was renamed', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true]);
    $dish->setDailyName('Strogonoff');
    $order = placeOrder([item($dish)]);
    $dish->setDailyName('Feijoada');

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => false, 'items' => [item($dish, 2)],
    ]);

    expect($updated->items->sole()->name_snapshot)->toBe('Prato do dia: Strogonoff');
});

it('keeps the snapshot of a line whose product was deleted', function () {
    $dish = kitchenProduct(['name' => 'Filé', 'price_cents' => 2400]);
    $order = placeOrder([item($dish)]);
    $itemId = $order->items->sole()->id;
    $dish->delete();

    $updated = app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => true,
        'items' => [['item_id' => $itemId, 'product_id' => 0, 'quantity' => 2, 'notes' => null]],
    ]);

    expect($updated->items->sole())->product_id->toBeNull()->name_snapshot->toBe('Filé')->quantity->toBe(2)
        ->and($updated->total_cents)->toBe(4800);
});

it('still refuses a new sold out product added in an edit', function () {
    $dish = kitchenProduct();
    $soldOut = kitchenProduct();
    $order = placeOrder([item($dish)]);
    $soldOut->toggleSoldOut();

    app(UpdateOrderItems::class)->handle($order, [
        'customer_name' => 'Maria', 'takeaway' => false, 'items' => [item($dish), item($soldOut)],
    ]);
})->throws(ValidationException::class);
