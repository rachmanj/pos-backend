<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Warehouse extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'type',
        'status',
        'address',
        'city',
        'state',
        'postal_code',
        'country',
        'latitude',
        'longitude',
        'phone',
        'email',
        'manager_name',
        'manager_phone',
        'total_area',
        'storage_area',
        'max_capacity',
        'current_utilization',
        'opening_time',
        'closing_time',
        'operating_days',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'total_area' => 'decimal:2',
        'storage_area' => 'decimal:2',
        'max_capacity' => 'integer',
        'current_utilization' => 'integer',
        'operating_days' => 'array',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
        'opening_time' => 'datetime:H:i',
        'closing_time' => 'datetime:H:i',
    ];

    // Relationships
    public function zones(): HasMany
    {
        return $this->hasMany(WarehouseZone::class)->orderBy('sort_order');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'from_warehouse_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(StockTransfer::class, 'to_warehouse_id');
    }

    public function purchaseReceipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
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

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // Accessors
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    public function getUtilizationPercentageAttribute(): float
    {
        if (!$this->max_capacity || $this->max_capacity <= 0) {
            return 0;
        }

        $totalStock = $this->stocks()->sum('quantity');
        return min(100, ($totalStock / $this->max_capacity) * 100);
    }

    public function getAvailableCapacityAttribute(): int
    {
        if (!$this->max_capacity) {
            return 0;
        }

        $totalStock = $this->stocks()->sum('quantity');
        return max(0, $this->max_capacity - $totalStock);
    }

    public function getIsOperationalAttribute(): bool
    {
        return $this->status === 'active' &&
            $this->operating_days &&
            count($this->operating_days) > 0;
    }

    // Methods
    public function getTotalStockValue(): float
    {
        return $this->stocks()->sum('total_value');
    }

    public function getTotalProducts(): int
    {
        return $this->stocks()->distinct('product_id')->count('product_id');
    }

    public function getLowStockCount(): int
    {
        return $this->stocks()
            ->whereColumn('quantity', '<=', 'minimum_stock')
            ->where('minimum_stock', '>', 0)
            ->count();
    }

    public function getActiveZonesCount(): int
    {
        return $this->zones()->where('status', 'active')->count();
    }

    public function canReceiveStock(int $quantity = 1): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if (!$this->max_capacity) {
            return true; // No capacity limit set
        }

        return $this->getAvailableCapacityAttribute() >= $quantity;
    }

    public function isOperatingToday(): bool
    {
        if (!$this->operating_days || empty($this->operating_days)) {
            return false;
        }

        $today = strtolower(now()->format('l')); // Get day name (e.g., 'monday')
        return in_array($today, array_map('strtolower', $this->operating_days));
    }

    public function getDistanceFrom(float $latitude, float $longitude): ?float
    {
        if (!$this->latitude || !$this->longitude) {
            return null;
        }

        // Haversine formula for calculating distance
        $earthRadius = 6371; // Earth's radius in kilometers

        $latFrom = deg2rad($this->latitude);
        $lonFrom = deg2rad($this->longitude);
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos($latFrom) * cos($latTo) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    // Static methods
    public static function getDefaultWarehouse(): ?self
    {
        return static::where('is_default', true)->first();
    }

    public static function getActiveWarehouses(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()->ordered()->get();
    }

    public static function generateCode(): string
    {
        $lastWarehouse = static::orderBy('id', 'desc')->first();
        $nextNumber = $lastWarehouse ? (int) substr($lastWarehouse->code, 2) + 1 : 1;

        return 'WH' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    }
}
