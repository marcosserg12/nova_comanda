<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function format(int $cents): string
    {
        return 'R$ '.number_format($cents / 100, 2, ',', '.');
    }

    public static function toDecimalString(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '');
    }

    public static function parseToCents(string|int|float|null $value): int
    {
        if ($value === null || $value === '') {
            throw new InvalidArgumentException('Preço vazio.');
        }

        if (is_int($value)) {
            return $value * 100;
        }

        $normalized = str_replace(['R$', ' '], '', (string) $value);

        if (str_contains($normalized, ',')) {
            $normalized = str_replace(['.', ','], ['', '.'], $normalized);
        }

        if (! is_numeric($normalized)) {
            throw new InvalidArgumentException("Preço inválido: {$value}");
        }

        return (int) round(((float) $normalized) * 100);
    }
}
