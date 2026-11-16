<?php

use App\Events\BoardChanged;
use App\Events\OrdersChanged;
use App\Models\Order;
use App\Support\OrderBroadcaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;

it('broadcasts a private change and a public board change with only safe fields', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $order = Order::factory()->create(['number' => 42, 'customer_name' => 'Maria Souza']);

    app(OrderBroadcaster::class)->changed($order, 'ready');

    Event::assertDispatched(OrdersChanged::class, fn (OrdersChanged $e) => $e->orderId === $order->id && $e->kind === 'ready');
    Event::assertDispatched(BoardChanged::class, function (BoardChanged $e) use ($order) {
        return $e->orderId === $order->id
            && $e->number === '042'
            && $e->firstName === 'Maria'
            && $e->status === 'preparing'
            && $e->kind === 'ready'
            && ! property_exists($e, 'customerName');
    });
});

it('waits for the surrounding transaction to commit', function () {
    Event::fake([OrdersChanged::class, BoardChanged::class]);
    $order = Order::factory()->create();

    DB::transaction(function () use ($order) {
        app(OrderBroadcaster::class)->changed($order, 'created');
        Event::assertNotDispatched(OrdersChanged::class);
    });

    Event::assertDispatched(OrdersChanged::class);
});

it('never lets a broadcasting failure break the caller', function () {
    Exceptions::fake();
    Event::listen(OrdersChanged::class, fn () => throw new RuntimeException('reverb fora do ar'));
    $order = Order::factory()->create();

    app(OrderBroadcaster::class)->changed($order, 'created');

    Exceptions::assertReported(RuntimeException::class);
});

it('uses the expected channels', function () {
    expect((new OrdersChanged(1, 'created'))->broadcastOn()[0]->name)->toBe('private-pedidos')
        ->and((new BoardChanged(1, 'created', '001', 'Maria', 'preparing'))->broadcastOn()[0]->name)->toBe('painel');
});
