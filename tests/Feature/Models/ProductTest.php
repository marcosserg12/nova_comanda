<?php

use App\Models\Product;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->travelTo(CarbonImmutable::parse('2026-11-13 11:00', 'America/Sao_Paulo')));

it('is sold out only on the day it was marked', function () {
    $product = Product::factory()->create();

    $product->toggleSoldOut();
    expect($product->fresh()->isSoldOut())->toBeTrue();

    $this->travelTo(CarbonImmutable::parse('2026-11-14 08:00', 'America/Sao_Paulo'));
    expect($product->fresh()->isSoldOut())->toBeFalse();
});

it('toggles sold out back off', function () {
    $product = Product::factory()->create();
    $product->toggleSoldOut();
    $product->fresh()->toggleSoldOut();

    expect($product->fresh()->isSoldOut())->toBeFalse();
});

it('needs a daily name until one is set for today', function () {
    $product = Product::factory()->create(['name' => 'Prato do dia', 'has_daily_name' => true]);

    expect($product->needsDailyName())->toBeTrue()
        ->and($product->displayName())->toBe('Prato do dia');

    $product->setDailyName('  Strogonoff ');

    expect($product->fresh()->needsDailyName())->toBeFalse()
        ->and($product->fresh()->displayName())->toBe('Prato do dia: Strogonoff');

    $this->travelTo(CarbonImmutable::parse('2026-11-14 08:00', 'America/Sao_Paulo'));
    expect($product->fresh()->needsDailyName())->toBeTrue();
});

it('reads and writes the price in reais through the price attribute', function () {
    $product = Product::factory()->create(['price_cents' => 2400]);

    expect($product->price)->toBe('24,00');

    $product->update(['price' => '25,50']);
    expect($product->fresh()->price_cents)->toBe(2550);
});

it('exposes sold out today as a writable boolean attribute', function () {
    $product = Product::factory()->create();

    $product->update(['sold_out_today' => true]);
    expect($product->fresh()->sold_out_today)->toBeTrue();

    $product->update(['sold_out_today' => false]);
    expect($product->fresh()->sold_out_today)->toBeFalse();
});
