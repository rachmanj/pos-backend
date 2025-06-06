<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class WarehouseZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'code',
        'name',
        'description',
        'type',
        'status',
        'aisle',
        'row',
        'level',
        'position',
        'area',
        'max_capacity',
        'current_stock',
        'utilization_percentage',
        'temperature_controlled',
        'min_temperature',
        'max_temperature',
        'humidity_controlled',
        'min_humidity',
        'max_humidity',
        'restricted_access',
        'allowed_product_categories',
        'restrictions',
        'sort_order',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'area' => 'decimal:2',
        'max_capacity' => 'integer',
        'current_stock' => 'integer',
        'temperature_controlled' => 'boolean',
        'min_temperature' => 'decimal:2',
        'max_temperature' => 'decimal:2',
        'humidity_controlled' => 'boolean',
        'min_humidity' => 'decimal:2',
        'max_humidity' => 'decimal:2',
        'restricted_access' => 'boolean',
        'allowed_product_categories' => 'array',
        'restrictions' => 'array',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'zone_type',
        'capacity_cubic_meters',
        'temperature_min',
        'temperature_max',
        'humidity_min',
        'humidity_max',
        'used_capacity',
        'available_capacity',
        'is_active',
    ];

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function transferItemsFrom(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'from_zone_id');
    }

    public function transferItemsTo(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'to_zone_id');
    }

    public function purchaseReceiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('max_capacity')
                    ->orWhereColumn('current_stock', '<', 'max_capacity');
            });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    // Accessors for Frontend Compatibility
    public function getZoneTypeAttribute(): string
    {
        return $this->type;
    }

    public function getCapacityCubicMetersAttribute(): ?int
    {
        return $this->max_capacity;
    }

    public function getTemperatureMinAttribute(): ?float
    {
        return $this->min_temperature;
    }

    public function getTemperatureMaxAttribute(): ?float
    {
        return $this->max_temperature;
    }

    public function getHumidityMinAttribute(): ?float
    {
        return $this->min_humidity;
    }

    public function getHumidityMaxAttribute(): ?float
    {
        return $this->max_humidity;
    }

    public function getUsedCapacityAttribute(): int
    {
        return $this->current_stock ?? 0;
    }

    public function getUtilizationPercentageAttribute(): float
    {
        // If there's already a value from the database, use it
        if (isset($this->attributes['utilization_percentage'])) {
            return (float) $this->attributes['utilization_percentage'];
        }

        // Otherwise calculate it
        if (!$this->max_capacity || $this->max_capacity == 0) {
            return 0.0;
        }
        return min(100.0, (($this->current_stock ?? 0) / $this->max_capacity) * 100);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->aisle ? "Aisle: {$this->aisle}" : null,
            $this->row ? "Row: {$this->row}" : null,
            $this->level ? "Level: {$this->level}" : null,
            $this->position ? "Position: {$this->position}" : null,
        ]);

        return implode(', ', $parts) ?: 'No specific location';
    }

    public function getAvailableCapacityAttribute(): int
    {
        if (!$this->max_capacity) {
            return PHP_INT_MAX; // Unlimited capacity
        }

        return max(0, $this->max_capacity - ($this->current_stock ?? 0));
    }

    public function getIsFullAttribute(): bool
    {
        if (!$this->max_capacity) {
            return false; // Unlimited capacity
        }

        return $this->current_stock >= $this->max_capacity;
    }

    public function getTemperatureRangeAttribute(): ?string
    {
        if (!$this->temperature_controlled) {
            return null;
        }

        $min = $this->min_temperature ? "{$this->min_temperature}°C" : 'No min';
        $max = $this->max_temperature ? "{$this->max_temperature}°C" : 'No max';

        return "{$min} - {$max}";
    }

    public function getHumidityRangeAttribute(): ?string
    {
        if (!$this->humidity_controlled) {
            return null;
        }

        $min = $this->min_humidity ? "{$this->min_humidity}%" : 'No min';
        $max = $this->max_humidity ? "{$this->max_humidity}%" : 'No max';

        return "{$min} - {$max}";
    }

    // Methods
    public function canAcceptProduct(Product $product, int $quantity = 1): bool
    {
        // Check zone status
        if ($this->status !== 'active') {
            return false;
        }

        // Check capacity
        if ($this->max_capacity && ($this->current_stock + $quantity) > $this->max_capacity) {
            return false;
        }

        // Check product category restrictions
        if ($this->restricted_access && $this->allowed_product_categories) {
            $productCategoryId = $product->category_id;
            if (!in_array($productCategoryId, $this->allowed_product_categories)) {
                return false;
            }
        }

        // Check environmental requirements (if product has special requirements)
        // This would need to be implemented based on product attributes

        return true;
    }

    public function updateUtilization(): void
    {
        if ($this->max_capacity && $this->max_capacity > 0) {
            $this->utilization_percentage = min(100, ($this->current_stock / $this->max_capacity) * 100);
            $this->save();
        }
    }

    public function getTotalStockValue(): float
    {
        return $this->stocks()->sum('total_value');
    }

    public function getTotalProducts(): int
    {
        return $this->stocks()->distinct('product_id')->count('product_id');
    }

    public function isTemperatureInRange(float $temperature): bool
    {
        if (!$this->temperature_controlled) {
            return true; // No temperature control required
        }

        $withinMin = !$this->min_temperature || $temperature >= $this->min_temperature;
        $withinMax = !$this->max_temperature || $temperature <= $this->max_temperature;

        return $withinMin && $withinMax;
    }

    public function isHumidityInRange(float $humidity): bool
    {
        if (!$this->humidity_controlled) {
            return true; // No humidity control required
        }

        $withinMin = !$this->min_humidity || $humidity >= $this->min_humidity;
        $withinMax = !$this->max_humidity || $humidity <= $this->max_humidity;

        return $withinMin && $withinMax;
    }

    // Static methods
    public static function getAvailableZones(int $warehouseId, ?string $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::byWarehouse($warehouseId)->available()->ordered();

        if ($type) {
            $query->byType($type);
        }

        return $query->get();
    }

    public static function generateCode(int $warehouseId): string
    {
        $lastZone = static::where('warehouse_id', $warehouseId)
            ->orderBy('id', 'desc')
            ->first();

        if (!$lastZone) {
            return 'A1';
        }

        // Simple increment logic - can be made more sophisticated
        $lastCode = $lastZone->code;
        $letter = substr($lastCode, 0, 1);
        $number = (int) substr($lastCode, 1);

        $number++;
        if ($number > 99) {
            $letter = chr(ord($letter) + 1);
            $number = 1;
        }

        return $letter . $number;
    }
}
