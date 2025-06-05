<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProductStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'current_stock',
        'reserved_stock',
        'available_stock',
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'reserved_stock' => 'integer',
        'available_stock' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereHas('product', function ($productQuery) {
            $productQuery->whereColumn('product_stocks.current_stock', '<=', 'products.min_stock_level');
        });
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('current_stock', '<=', 0);
    }

    public function scopeOverstock(Builder $query): Builder
    {
        return $query->whereHas('product', function ($productQuery) {
            $productQuery->whereColumn('product_stocks.current_stock', '>', 'products.max_stock_level')
                ->whereNotNull('products.max_stock_level');
        });
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= ($this->product->min_stock_level ?? 0);
    }

    public function isOutOfStock(): bool
    {
        return $this->current_stock <= 0;
    }

    public function isOverstocked(): bool
    {
        $maxLevel = $this->product->max_stock_level;
        return $maxLevel && $this->current_stock > $maxLevel;
    }

    public function canReserve(int $quantity): bool
    {
        return $this->available_stock >= $quantity;
    }

    public function reserve(int $quantity): bool
    {
        if (!$this->canReserve($quantity)) {
            return false;
        }

        $this->increment('reserved_stock', $quantity);
        $this->decrement('available_stock', $quantity);

        return true;
    }

    public function release(int $quantity): bool
    {
        $releaseQuantity = min($quantity, $this->reserved_stock);

        if ($releaseQuantity <= 0) {
            return false;
        }

        $this->decrement('reserved_stock', $releaseQuantity);
        $this->increment('available_stock', $releaseQuantity);

        return true;
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        if ($this->isOverstocked()) {
            return 'overstock';
        }

        return 'normal';
    }

    public function getStockValueAttribute(): float
    {
        return $this->current_stock * ($this->product->cost_price ?? 0);
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($stock) {
            // Ensure available stock is never negative and doesn't exceed current stock
            $stock->available_stock = max(0, min(
                $stock->current_stock - $stock->reserved_stock,
                $stock->available_stock
            ));
        });
    }
}
