<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A name suggested in the counter's "Nome do cliente" field: common Brazilian names plus every
 * name already used in an order (those come first).
 *
 * @property string $name
 * @property string $search_key
 * @property int $uses
 * @property Carbon|null $last_used_at
 */
class CustomerName extends Model
{
    public const SUGGESTION_LIMIT = 5000;

    private const LOWERCASE_PARTICLES = ['da', 'das', 'de', 'do', 'dos', 'e'];

    protected $fillable = ['name', 'search_key', 'uses', 'last_used_at'];

    protected function casts(): array
    {
        return ['uses' => 'integer', 'last_used_at' => 'datetime'];
    }

    /** Accent- and case-insensitive key: "João  da Silva" → "joao da silva". */
    public static function keyFor(string $name): string
    {
        return Str::lower(Str::ascii(Str::squish($name)));
    }

    /**
     * Capitalizes words typed in lower case ("zuleide Paiva" → "Zuleide Paiva", "maria da silva" →
     * "Maria da Silva") and names typed all in upper case; mixed words like "McDonald" are kept.
     */
    public static function pretty(string $name): string
    {
        $name = Str::squish($name);

        if ($name === Str::upper($name)) {
            $name = Str::lower($name);
        }

        $words = explode(' ', $name);

        foreach ($words as $i => $word) {
            $particle = $i > 0 && in_array($word, self::LOWERCASE_PARTICLES, true);

            if ($word === Str::lower($word) && ! $particle) {
                $words[$i] = Str::ucfirst($word);
            }
        }

        return implode(' ', $words);
    }

    /** Counts one more order for this name, creating it when new. */
    public static function record(string $name, ?Carbon $at = null): void
    {
        $display = self::pretty($name);
        $key = self::keyFor($display);

        if ($key === '' || Str::length($display) > 60) {
            return;
        }

        $at ??= now();
        $existing = self::query()->where('search_key', $key)->first();

        if ($existing === null) {
            self::query()->create(['name' => $display, 'search_key' => $key, 'uses' => 1, 'last_used_at' => $at]);

            return;
        }

        $existing->uses++;
        $existing->last_used_at = $at;

        // Prefer the spelling with accents ("João" over "Joao").
        if (Str::ascii($existing->name) === $existing->name && Str::ascii($display) !== $display) {
            $existing->name = $display;
        }

        $existing->save();
    }

    /** @return list<string> Names already used first (most used, most recent), then common names. */
    public static function suggestionList(): array
    {
        return self::query()
            ->orderByDesc('uses')
            ->orderByDesc('last_used_at')
            ->orderBy('id')
            ->limit(self::SUGGESTION_LIMIT)
            ->pluck('name')
            ->all();
    }

    /** @return list<string> Completes the second word: "Bianca S" → "Bianca Silva". */
    public static function commonSurnames(): array
    {
        return require database_path('data/sobrenomes-brasileiros.php');
    }

    public static function seedCommonNames(): void
    {
        $now = now();
        $rows = [];

        foreach (require database_path('data/nomes-brasileiros.php') as $name) {
            $rows[self::keyFor($name)] = ['name' => $name, 'search_key' => self::keyFor($name), 'uses' => 0, 'created_at' => $now, 'updated_at' => $now];
        }

        foreach (array_chunk(array_values($rows), 200) as $chunk) {
            self::query()->insertOrIgnore($chunk);
        }
    }

    /** Imports the names of orders placed before this table existed. */
    public static function backfillFromOrders(): void
    {
        DB::table('orders')
            ->select('customer_name', 'created_at')
            ->orderBy('id')
            ->lazy()
            ->each(fn (object $order) => self::record($order->customer_name, Carbon::parse($order->created_at)));
    }
}
