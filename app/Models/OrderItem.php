<?php

namespace App\Models;

use App\Enums\Destination;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'product_id', 'name_snapshot', 'unit_price_cents', 'quantity', 'notes', 'destination', 'position',
    ];

    protected function casts(): array
    {
        return [
            'destination' => Destination::class,
            'unit_price_cents' => 'integer',
            'quantity' => 'integer',
            'position' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
