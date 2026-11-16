<?php

namespace App\Enums;

enum OrderSource: string
{
    case Balcao = 'balcao';
    case Totem = 'totem';

    public function label(): string
    {
        return match ($this) {
            self::Balcao => 'Balcão',
            self::Totem => 'Totem',
        };
    }
}
