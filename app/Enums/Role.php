<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Balcao = 'balcao';
    case Cozinha = 'cozinha';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Balcao => 'Balcão',
            self::Cozinha => 'Cozinha',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
