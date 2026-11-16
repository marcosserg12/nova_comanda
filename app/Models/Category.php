<?php

namespace App\Models;

use App\Enums\Destination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'destination', 'position', 'active'];

    protected function casts(): array
    {
        return ['destination' => Destination::class, 'active' => 'boolean', 'position' => 'integer'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)->orderBy('position');
    }

    public function quickNotes(): HasMany
    {
        return $this->hasMany(QuickNote::class)->orderBy('position');
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
