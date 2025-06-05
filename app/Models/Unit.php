<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'symbol',
        'base_unit_id',
        'conversion_factor',
    ];

    protected $casts = [
        'conversion_factor' => 'decimal:6',
    ];

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function derivedUnits(): HasMany
    {
        return $this->hasMany(Unit::class, 'base_unit_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isBaseUnit(): bool
    {
        return is_null($this->base_unit_id);
    }

    public function convertToBaseUnit(float $quantity): float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }

        return $quantity * $this->conversion_factor;
    }

    public function convertFromBaseUnit(float $quantity): float
    {
        if ($this->isBaseUnit()) {
            return $quantity;
        }

        return $quantity / $this->conversion_factor;
    }

    public function convertTo(Unit $targetUnit, float $quantity): float
    {
        if ($this->id === $targetUnit->id) {
            return $quantity;
        }

        // Convert to base unit first
        $baseQuantity = $this->convertToBaseUnit($quantity);

        // Then convert to target unit
        return $targetUnit->convertFromBaseUnit($baseQuantity);
    }

    public function getDisplayNameAttribute(): string
    {
        return "{$this->name} ({$this->symbol})";
    }
}
