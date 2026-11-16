<?php

namespace App\Models;

use App\Support\BusinessDay;
use App\Support\Money;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'name', 'price_cents', 'price', 'has_daily_name', 'daily_name', 'daily_name_date',
        'sold_out_date', 'sold_out_today', 'image_path', 'active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'has_daily_name' => 'boolean',
            'daily_name_date' => 'date',
            'sold_out_date' => 'date',
            'active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isSoldOut(): bool
    {
        return $this->sold_out_date !== null && $this->sold_out_date->isSameDay(BusinessDay::today());
    }

    public function toggleSoldOut(): void
    {
        $this->forceFill(['sold_out_date' => $this->isSoldOut() ? null : BusinessDay::todayString()])->save();
    }

    public function dailyNameToday(): ?string
    {
        if (! $this->has_daily_name || blank($this->daily_name) || $this->daily_name_date === null) {
            return null;
        }

        return $this->daily_name_date->isSameDay(BusinessDay::today()) ? $this->daily_name : null;
    }

    public function needsDailyName(): bool
    {
        return $this->has_daily_name && $this->dailyNameToday() === null;
    }

    public function displayName(): string
    {
        $daily = $this->dailyNameToday();

        return $daily === null ? $this->name : "{$this->name}: {$daily}";
    }

    public function setDailyName(string $name): void
    {
        $this->forceFill(['daily_name' => trim($name), 'daily_name_date' => BusinessDay::todayString()])->save();
    }

    protected function price(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value, array $attributes) => isset($attributes['price_cents'])
                ? Money::toDecimalString((int) $attributes['price_cents'])
                : null,
            set: fn (mixed $value) => ['price_cents' => Money::parseToCents($value)],
        );
    }

    protected function soldOutToday(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isSoldOut(),
            set: fn (mixed $value) => [
                'sold_out_date' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? BusinessDay::todayString() : null,
            ],
        );
    }
}
