<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BusinessDay
{
    public const TIMEZONE = 'America/Sao_Paulo';

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE)->startOfDay();
    }

    public static function todayString(): string
    {
        return self::today()->toDateString();
    }
}
