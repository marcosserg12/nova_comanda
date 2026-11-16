<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    public const READY_MINUTES = 'ready_minutes';

    public const RESTAURANT_NAME = 'restaurant_name';

    public const STATION_LEFT_WIDTH = 'station_left_width';

    public const STATION_RIGHT_WIDTH = 'station_right_width';

    public const DEFAULTS = [
        self::READY_MINUTES => 5,
        self::RESTAURANT_NAME => 'Nana Lanches',
        self::STATION_LEFT_WIDTH => 1920,
        self::STATION_RIGHT_WIDTH => 1920,
    ];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever("setting.{$key}", fn () => static::query()->find($key)?->value);

        return $value ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting.{$key}");
    }
}
