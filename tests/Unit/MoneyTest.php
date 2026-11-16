<?php

use App\Support\Money;

it('formats cents as Brazilian reais', function () {
    expect(Money::format(2400))->toBe('R$ 24,00')
        ->and(Money::format(123456))->toBe('R$ 1.234,56')
        ->and(Money::toDecimalString(2550))->toBe('25,50');
});

it('parses prices typed by people into cents', function (string|int|float $typed, int $cents) {
    expect(Money::parseToCents($typed))->toBe($cents);
})->with([
    ['24', 2400], ['24,5', 2450], ['24,50', 2450], ['24.50', 2450], ['1.234,56', 123456], ['R$ 17,00', 1700], [24, 2400], [24.5, 2450],
]);

it('rejects invalid prices', function () {
    Money::parseToCents('abc');
})->throws(InvalidArgumentException::class);
