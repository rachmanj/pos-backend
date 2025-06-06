<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $fillable = [
        'warehouse_id',
        'warehouse_zone_id',
        'product_id',
        'unit_id',
        'quantity',
        'reserved_quantity',
        'available_quantity',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'reorder_quantity',
        'average_cost',
        'last_cost',
        'total_value',
        'bin_location',
        'lot_number',
        'expiry_date',
        'manufacture_date',
        'status',
        'is_active',
        'notes',
        'last_movement_at',
        'last_movement_by',
        'last_movement_quantity',
        'last_movement_type',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'warehouse_zone_id' => 'integer',
        'product_id' => 'integer',
        'unit_id' => 'integer',
        'quantity' => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'available_quantity' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
        'maximum_stock' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'reorder_quantity' => 'decimal:4',
        'average_cost' => 'decimal:4',
        'last_cost' => 'decimal:4',
        'total_value' => 'decimal:4',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
        'is_active' => 'boolean',
        'last_movement_at' => 'datetime',
        'last_movement_by' => 'integer',
        'last_movement_quantity' => 'decimal:4',
    ];

    // Relationships
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function warehouseZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function lastMovementUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_movement_by');
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeByWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByZone(Builder $query, int $zoneId): Builder
    {
        return $query->where('warehouse_zone_id', $zoneId);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available')
            ->where('quantity', '>', 0)
            ->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('quantity', '<=', 'minimum_stock')
            ->where('minimum_stock', '>', 0)
            ->where('is_active', true);
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>', now())
            ->where('is_active', true);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->where('is_active', true);
    }

    // Accessors
    public function getFullLocationAttribute(): string
    {
        $parts = [];

        if ($this->warehouse) {
            $parts[] = "Warehouse: {$this->warehouse->name}";
        }

        if ($this->warehouseZone) {
            $parts[] = "Zone: {$this->warehouseZone->code}";
        }

        if ($this->bin_location) {
            $parts[] = "Bin: {$this->bin_location}";
        }

        return implode(', ', $parts) ?: 'No location specified';
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->minimum_stock > 0 && $this->quantity <= $this->minimum_stock;
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date && $this->expiry_date < now();
    }

    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }

        return $this->expiry_date <= now()->addDays(30) && $this->expiry_date > now();
    }

    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expiry_date) {
            return null;
        }

        return now()->diffInDays($this->expiry_date, false);
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->is_expired) {
            return 'expired';
        }

        if ($this->is_expiring_soon) {
            return 'expiring_soon';
        }

        if ($this->is_low_stock) {
            return 'low_stock';
        }

        if ($this->quantity <= 0) {
            return 'out_of_stock';
        }

        return 'in_stock';
    }

    // Methods
    public function updateAvailableQuantity(): void
    {
        $this->available_quantity = max(0, $this->quantity - $this->reserved_quantity);
        $this->save();
    }

    public function updateTotalValue(): void
    {
        $this->total_value = $this->quantity * $this->average_cost;
        $this->save();
    }

    public function reserveStock(float $quantity): bool
    {
        if ($this->available_quantity < $quantity) {
            return false;
        }

        $this->reserved_quantity += $quantity;
        $this->updateAvailableQuantity();

        return true;
    }

    public function releaseReservation(float $quantity): void
    {
        $this->reserved_quantity = max(0, $this->reserved_quantity - $quantity);
        $this->updateAvailableQuantity();
    }

    public function adjustStock(float $quantity, string $type, ?int $userId = null, ?string $notes = null): void
    {
        $oldQuantity = $this->quantity;

        if ($type === 'in') {
            $this->quantity += $quantity;
        } else {
            $this->quantity = max(0, $this->quantity - $quantity);
        }

        // Update tracking information
        $this->last_movement_at = now();
        $this->last_movement_by = $userId;
        $this->last_movement_quantity = $quantity;
        $this->last_movement_type = $type;

        if ($notes) {
            $this->notes = $notes;
        }

        $this->updateAvailableQuantity();
        $this->updateTotalValue();

        // Create stock movement record
        StockMovement::create([
            'warehouse_id' => $this->warehouse_id,
            'warehouse_zone_id' => $this->warehouse_zone_id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'type' => $type,
            'quantity' => $quantity,
            'previous_quantity' => $oldQuantity,
            'new_quantity' => $this->quantity,
            'cost_per_unit' => $this->average_cost,
            'total_cost' => $quantity * $this->average_cost,
            'reference_type' => 'adjustment',
            'notes' => $notes,
            'created_by' => $userId,
            'bin_location' => $this->bin_location,
        ]);
    }

    public function updateAverageCost(float $newCost, float $newQuantity): void
    {
        if ($this->quantity <= 0) {
            $this->average_cost = $newCost;
        } else {
            $totalValue = ($this->quantity * $this->average_cost) + ($newQuantity * $newCost);
            $totalQuantity = $this->quantity + $newQuantity;
            $this->average_cost = $totalQuantity > 0 ? $totalValue / $totalQuantity : $newCost;
        }

        $this->last_cost = $newCost;
        $this->updateTotalValue();
    }

    public function canFulfillOrder(float $requestedQuantity): bool
    {
        return $this->status === 'available' &&
            $this->is_active &&
            $this->available_quantity >= $requestedQuantity &&
            !$this->is_expired;
    }

    // Static methods
    public static function getStockByLocation(int $warehouseId, int $productId, ?int $unitId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = static::byWarehouse($warehouseId)
            ->byProduct($productId)
            ->active();

        if ($unitId) {
            $query->where('unit_id', $unitId);
        }

        return $query->get();
    }

    public static function getTotalStock(int $productId, ?int $warehouseId = null): float
    {
        $query = static::byProduct($productId)->active();

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        return $query->sum('quantity');
    }

    public static function getAvailableStock(int $productId, ?int $warehouseId = null): float
    {
        $query = static::byProduct($productId)->available();

        if ($warehouseId) {
            $query->byWarehouse($warehouseId);
        }

        return $query->sum('available_quantity');
    }

    public static function findOrCreateStock(array $attributes): self
    {
        $stock = static::where([
            'warehouse_id' => $attributes['warehouse_id'],
            'product_id' => $attributes['product_id'],
            'unit_id' => $attributes['unit_id'],
            'lot_number' => $attributes['lot_number'] ?? null,
        ])->first();

        if (!$stock) {
            $stock = static::create($attributes);
        }

        return $stock;
    }
}
