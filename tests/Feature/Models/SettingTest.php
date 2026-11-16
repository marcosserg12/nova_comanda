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
