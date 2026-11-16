<?php

use App\Actions\Orders\PlaceOrder;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    Event::fake([OrdersChanged::class, BoardChanged::class]);
});

it('numbers orders per day starting at 1 and restarts the next day', function () {
    $dish = kitchenProduct();

    expect(placeOrder([item($dish)])->number)->toBe(1)
        ->and(placeOrder([item($dish)])->number)->toBe(2);

    $this->travelTo(CarbonImmutable::parse('2026-11-14 11:00', 'America/Sao_Paulo'));

    expect(placeOrder([item($dish)])->number)->toBe(1);
});

it('computes the total on the server and freezes name, price and destination', function () {
    $dish = kitchenProduct(['name' => 'Filé de frango', 'price_cents' => 2400]);
    $soda = cabineProduct(['name' => 'Coca lata', 'price_cents' => 600]);

    $order = placeOrder([item($dish, 2, 'Sem arroz'), item($soda)], ['customer_name' => '  João  ', 'takeaway' => true]);
    $dish->update(['price_cents' => 9900, 'name' => 'Outro nome']);

    $order = $order->fresh('items');
    expect($order->total_cents)->toBe(5400)
        ->and($order->customer_name)->toBe('João')
        ->and($order->takeaway)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::Preparing)
        ->and($order->items->pluck('name_snapshot')->all())->toBe(['Filé de frango', 'Coca lata'])
        ->and($order->items->first()->unit_price_cents)->toBe(2400)
        ->and($order->items->first()->notes)->toBe('Sem arroz')
        ->and($order->items->last()->destination->value)->toBe('cabine');
});

it('stores the dish of the day name in the snapshot', function () {
    $dish = kitchenProduct(['name' => 'Prato do dia', 'has_daily_name' => true]);
    $dish->setDailyName('Feijoada');

    expect(placeOrder([item($dish)])->items->first()->name_snapshot)->toBe('Prato do dia: Feijoada');
});

it('returns the existing order when the same uuid is sent twice', function () {
    $dish = kitchenProduct();
    $input = orderInput([item($dish)]);

    $first = app(PlaceOrder::class)->handle($input);
    $second = app(PlaceOrder::class)->handle($input);

    expect($second->id)->toBe($first->id)->and(Order::query()->count())->toBe(1);
});

it('delivers cabine-only orders immediately', function () {
    $order = placeOrder([item(cabineProduct())]);

    expect($order->status)->toBe(OrderStatus::Delivered)->and($order->delivered_at)->not->toBeNull();
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'delivered');
});

it('rejects sold out products without consuming a number', function () {
    $dish = kitchenProduct();
    $dish->toggleSoldOut();

    expect(fn () => placeOrder([item($dish)]))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(0);

    expect(placeOrder([item(kitchenProduct())])->number)->toBe(1);
});

it('rejects a dish of the day without today name', function () {
    $dish = kitchenProduct(['has_daily_name' => true]);

    placeOrder([item($dish)]);
})->throws(ValidationException::class);

it('requires a customer name and at least one item', function () {
    expect(fn () => placeOrder([], ['customer_name' => '']))->toThrow(ValidationException::class);
});

it('records who placed the order and the source, and broadcasts it', function () {
    $user = userWithRole(Role::Balcao);

    $order = placeOrder([item(kitchenProduct())], by: $user);

    expect($order->created_by)->toBe($user->id)->and($order->source)->toBe(OrderSource::Balcao);
    Event::assertDispatched(OrdersChanged::class, fn ($e) => $e->kind === 'created' && $e->orderId === $order->id);
});
