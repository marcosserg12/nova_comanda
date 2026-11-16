<?php

use App\Models\Setting;

it('returns the built-in default when a setting was never saved', function () {
    expect(Setting::get(Setting::READY_MINUTES))->toBe(5)
        ->and(Setting::get(Setting::RESTAURANT_NAME))->toBe('Nana Lanches')
        ->and(Setting::get('unknown', 'fallback'))->toBe('fallback');
});

it('stores values and refreshes the cache on put', function () {
    expect(Setting::get(Setting::READY_MINUTES))->toBe(5);

    Setting::put(Setting::READY_MINUTES, 8);

    expect(Setting::get(Setting::READY_MINUTES))->toBe(8);
});

it('serves the new value after a cached value is replaced', function () {
    Setting::put(Setting::READY_MINUTES, 7);
    expect(Setting::get(Setting::READY_MINUTES))->toBe(7);

    Setting::put(Setting::READY_MINUTES, 9);
    expect(Setting::get(Setting::READY_MINUTES))->toBe(9);
});

it('defaults the station widths to the 18.5 inch monitor', function () {
    expect(Setting::get(Setting::STATION_LEFT_WIDTH))->toBe(1366)
        ->and(Setting::get(Setting::STATION_RIGHT_WIDTH))->toBe(1366);
});
