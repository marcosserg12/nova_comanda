<?php

use App\Models\Order;
use Carbon\CarbonImmutable;

it('pads the order number to three digits', function () {
    expect(Order::factory()->make(['number' => 7])->displayNumber())->toBe('007')
        ->and(Order::factory()->make(['number' => 123])->displayNumber())->toBe('123');
});

it('extracts a capitalized first name', function (string $name, string $first) {
    expect(Order::factory()->make(['customer_name' => $name])->firstName())->toBe($first);
})->with([
    ['maria  souza', 'Maria'],
    ['JOÃO', 'João'],
    ['  ana paula ', 'Ana'],
]);

it('scopes orders to the current business day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo'));
    $today = Order::factory()->create(['business_date' => '2026-11-13', 'number' => 1]);
    Order::factory()->create(['business_date' => '2026-11-12', 'number' => 1]);

    expect(Order::query()->today()->pluck('id')->all())->toBe([$today->id]);
});
