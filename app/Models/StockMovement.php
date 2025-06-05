<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'movement_type' => 'string',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeIn(Builder $query): Builder
    {
        return $query->where('movement_type', 'in');
    }

    public function scopeOut(Builder $query): Builder
    {
        return $query->where('movement_type', 'out');
    }

    public function scopeAdjustment(Builder $query): Builder
    {
        return $query->where('movement_type', 'adjustment');
    }

    public function scopeByProduct(Builder $query, $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByReference(Builder $query, string $type, $id): Builder
    {
        return $query->where('reference_type', $type)
            ->where('reference_id', $id);
    }

    public function isInbound(): bool
    {
        return $this->movement_type === 'in';
    }

    public function isOutbound(): bool
    {
        return $this->movement_type === 'out';
    }

    public function isAdjustment(): bool
    {
        return $this->movement_type === 'adjustment';
    }

    public function getSignedQuantityAttribute(): int
    {
        return match ($this->movement_type) {
            'in' => $this->quantity,
            'out' => -$this->quantity,
            'adjustment' => $this->quantity, // Can be positive or negative
            default => 0,
        };
    }

    public function getTotalCostAttribute(): float
    {
        return $this->quantity * ($this->unit_cost ?? 0);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($movement) {
            $movement->updateProductStock();
        });
    }

    public function updateProductStock(): void
    {
        $product = $this->product;
        $stock = $product->stock;

        if (!$stock) {
            $stock = ProductStock::create([
                'product_id' => $product->id,
                'current_stock' => 0,
                'reserved_stock' => 0,
                'available_stock' => 0,
            ]);
        }

        // Calculate new stock based on movement type
        $newStock = match ($this->movement_type) {
            'in' => $stock->current_stock + $this->quantity,
            'out' => $stock->current_stock - $this->quantity,
            'adjustment' => $this->quantity, // Direct set for adjustments
            default => $stock->current_stock,
        };

        $stock->update([
            'current_stock' => max(0, $newStock),
            'available_stock' => max(0, $newStock - $stock->reserved_stock),
        ]);
    }
}
