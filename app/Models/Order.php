<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Support\BusinessDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid', 'business_date', 'number', 'customer_name', 'takeaway', 'status', 'source', 'total_cents',
        'created_by', 'ready_at', 'delivered_at', 'canceled_at', 'edited_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'number' => 'integer',
            'takeaway' => 'boolean',
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'total_cents' => 'integer',
            'ready_at' => 'datetime',
            'delivered_at' => 'datetime',
            'canceled_at' => 'datetime',
            'edited_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeToday(Builder $query): void
    {
        $query->whereDate('business_date', BusinessDay::todayString());
    }

    public function displayNumber(): string
    {
        return str_pad((string) $this->number, 3, '0', STR_PAD_LEFT);
    }

    public function firstName(): string
    {
        $parts = preg_split('/\s+/u', trim((string) $this->customer_name)) ?: [''];

        return Str::ucfirst(Str::lower($parts[0]));
    }
}
