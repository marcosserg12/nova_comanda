<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Preparing => 'Preparando',
            self::Ready => 'Pronto',
            self::Delivered => 'Entregue',
            self::Canceled => 'Cancelado',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
