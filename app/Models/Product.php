<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'sku',
        'barcode',
        'category_id',
        'unit_id',
        'cost_price',
        'selling_price',
        'min_stock_level',
        'max_stock_level',
        'tax_rate',
        'image',
        'status',
    ];

    protected $casts = [
        'cost_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'status' => 'string',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByCategory(Builder $query, $categoryId): Builder
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithLowStock(Builder $query): Builder
    {
        return $query->whereHas('stock', function ($stockQuery) {
            $stockQuery->whereColumn('current_stock', '<=', 'products.min_stock_level');
        });
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function getCurrentStockAttribute(): int
    {
        return $this->stock?->current_stock ?? 0;
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->stock?->available_stock ?? 0;
    }

    public function getReservedStockAttribute(): int
    {
        return $this->stock?->reserved_stock ?? 0;
    }

    public function isLowStock(): bool
    {
        return $this->current_stock <= $this->min_stock_level;
    }

    public function isOutOfStock(): bool
    {
        return $this->current_stock <= 0;
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->cost_price <= 0) {
            return 0;
        }

        return (($this->selling_price - $this->cost_price) / $this->cost_price) * 100;
    }

    public function getProfitAttribute(): float
    {
        return $this->selling_price - $this->cost_price;
    }

    public function getTotalValueAttribute(): float
    {
        return $this->current_stock * $this->cost_price;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->selling_price, 2);
    }

    public function getFormattedCostAttribute(): string
    {
        return number_format($this->cost_price, 2);
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($product) {
            // Create stock record when product is created
            $initialStock = request('initial_stock', 0);

            ProductStock::create([
                'product_id' => $product->id,
                'current_stock' => $initialStock,
                'reserved_stock' => 0,
                'available_stock' => $initialStock,
            ]);

            // Create initial stock movement if initial stock > 0
            if ($initialStock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => 'in',
                    'quantity' => $initialStock,
                    'reference_type' => 'initial_stock',
                    'reference_id' => $product->id,
                    'notes' => 'Initial stock entry',
                    'user_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                ]);
            }
        });
    }
}
