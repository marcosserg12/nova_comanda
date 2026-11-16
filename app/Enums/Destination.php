<?php

namespace App\Enums;

enum Destination: string
{
    case Cozinha = 'cozinha';
    case Cabine = 'cabine';

    public function label(): string
    {
        return match ($this) {
            self::Cozinha => 'Cozinha',
            self::Cabine => 'Cabine',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $case) => [$case->value => $case->label()])->all();
    }
}
