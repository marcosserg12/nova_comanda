<?php

use App\Support\BusinessDay;
use Carbon\CarbonImmutable;

afterEach(fn () => CarbonImmutable::setTestNow());

it('uses the São Paulo calendar day', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-14 02:30:00', 'UTC'));

    expect(BusinessDay::todayString())->toBe('2026-11-13')
        ->and(BusinessDay::today()->format('H:i'))->toBe('00:00');
});
