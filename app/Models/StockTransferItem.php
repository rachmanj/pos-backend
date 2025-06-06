<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class StockTransferItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_transfer_id',
        'product_id',
        'unit_id',
        'from_warehouse_stock_id',
        'from_zone_id',
        'from_bin_location',
        'to_zone_id',
        'to_bin_location',
        'requested_quantity',
        'shipped_quantity',
        'received_quantity',
        'damaged_quantity',
        'variance_quantity',
        'unit_cost',
        'total_cost',
        'lot_number',
        'expiry_date',
        'manufacture_date',
        'status',
        'quality_status',
        'quality_notes',
        'shipped_at',
        'received_at',
        'shipped_by',
        'received_by',
        'notes',
        'custom_fields',
        'line_number',
    ];

    protected $casts = [
        'stock_transfer_id' => 'integer',
        'product_id' => 'integer',
        'unit_id' => 'integer',
        'from_warehouse_stock_id' => 'integer',
        'from_zone_id' => 'integer',
        'to_zone_id' => 'integer',
        'requested_quantity' => 'decimal:4',
        'shipped_quantity' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'damaged_quantity' => 'decimal:4',
        'variance_quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'total_cost' => 'decimal:4',
        'expiry_date' => 'date',
        'manufacture_date' => 'date',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'shipped_by' => 'integer',
        'received_by' => 'integer',
        'custom_fields' => 'array',
        'line_number' => 'integer',
    ];

    // Relationships
    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function fromWarehouseStock(): BelongsTo
    {
        return $this->belongsTo(WarehouseStock::class, 'from_warehouse_stock_id');
    }

    public function fromZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'from_zone_id');
    }

    public function toZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class, 'to_zone_id');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // Scopes
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByQualityStatus(Builder $query, string $qualityStatus): Builder
    {
        return $query->where('quality_status', $qualityStatus);
    }

    public function scopeByProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeByTransfer(Builder $query, int $transferId): Builder
    {
        return $query->where('stock_transfer_id', $transferId);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeShipped(Builder $query): Builder
    {
        return $query->where('status', 'shipped');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', 'received');
    }

    public function scopeDamaged(Builder $query): Builder
    {
        return $query->where('quality_status', 'damaged');
    }

    public function scopeWithVariance(Builder $query): Builder
    {
        return $query->where('variance_quantity', '!=', 0);
    }

    // Accessors
    public function getCanBeShippedAttribute(): bool
    {
        return $this->status === 'pending' &&
            $this->stockTransfer->can_be_shipped;
    }

    public function getCanBeReceivedAttribute(): bool
    {
        return $this->status === 'in_transit' &&
            $this->stockTransfer->can_be_received;
    }

    public function getHasVarianceAttribute(): bool
    {
        return abs($this->variance_quantity) > 0.0001; // Account for floating point precision
    }

    public function getVariancePercentageAttribute(): float
    {
        if ($this->shipped_quantity <= 0) {
            return 0;
        }

        return ($this->variance_quantity / $this->shipped_quantity) * 100;
    }

    public function getIsCompletelyReceivedAttribute(): bool
    {
        return abs($this->received_quantity - $this->shipped_quantity) < 0.0001;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->received_quantity > 0 &&
            $this->received_quantity < $this->shipped_quantity;
    }

    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'gray',
            'shipped' => 'blue',
            'in_transit' => 'purple',
            'received' => 'green',
            'damaged' => 'red',
            'cancelled' => 'red',
            default => 'gray',
        };
    }

    public function getQualityBadgeColorAttribute(): string
    {
        return match ($this->quality_status) {
            'good' => 'green',
            'damaged' => 'red',
            'expired' => 'orange',
            'quarantine' => 'yellow',
            default => 'gray',
        };
    }

    // Methods
    public function ship(int $userId, float $quantity, ?string $notes = null): bool
    {
        if (!$this->can_be_shipped) {
            return false;
        }

        if ($quantity > $this->requested_quantity) {
            return false;
        }

        $this->shipped_quantity = $quantity;
        $this->status = 'in_transit';
        $this->shipped_by = $userId;
        $this->shipped_at = now();

        if ($notes) {
            $this->notes = $notes;
        }

        $this->save();

        // Update source warehouse stock
        if ($this->fromWarehouseStock) {
            $this->fromWarehouseStock->adjustStock($quantity, 'out', $userId, "Transfer to {$this->stockTransfer->toWarehouse->name}");
        }

        return true;
    }

    public function receive(int $userId, float $quantity, ?string $qualityStatus = 'good', ?string $notes = null): bool
    {
        if (!$this->can_be_received) {
            return false;
        }

        if ($quantity > $this->shipped_quantity) {
            return false;
        }

        $this->received_quantity = $quantity;
        $this->variance_quantity = $quantity - $this->shipped_quantity;
        $this->status = 'received';
        $this->quality_status = $qualityStatus;
        $this->received_by = $userId;
        $this->received_at = now();

        if ($notes) {
            $this->quality_notes = $notes;
        }

        $this->save();

        // Create or update destination warehouse stock
        $this->createDestinationStock($quantity, $userId);

        // Update transfer completion
        $this->stockTransfer->updateCompletionPercentage();

        return true;
    }

    public function markDamaged(int $userId, float $damagedQuantity, ?string $notes = null): void
    {
        $this->damaged_quantity = $damagedQuantity;
        $this->quality_status = 'damaged';
        $this->quality_notes = $notes;
        $this->received_by = $userId;
        $this->received_at = now();

        // Adjust received quantity
        $this->received_quantity = max(0, $this->shipped_quantity - $damagedQuantity);
        $this->variance_quantity = $this->received_quantity - $this->shipped_quantity;

        $this->save();

        // Create destination stock only for non-damaged quantity
        if ($this->received_quantity > 0) {
            $this->createDestinationStock($this->received_quantity, $userId);
        }
    }

    public function updateTotalCost(): void
    {
        $this->total_cost = $this->requested_quantity * $this->unit_cost;
        $this->save();
    }

    private function createDestinationStock(float $quantity, int $userId): void
    {
        $destinationWarehouse = $this->stockTransfer->toWarehouse;

        $stockData = [
            'warehouse_id' => $destinationWarehouse->id,
            'warehouse_zone_id' => $this->to_zone_id,
            'product_id' => $this->product_id,
            'unit_id' => $this->unit_id,
            'lot_number' => $this->lot_number,
            'quantity' => 0,
            'average_cost' => $this->unit_cost,
            'last_cost' => $this->unit_cost,
            'bin_location' => $this->to_bin_location,
            'expiry_date' => $this->expiry_date,
            'manufacture_date' => $this->manufacture_date,
        ];

        $destinationStock = WarehouseStock::findOrCreateStock($stockData);
        $destinationStock->adjustStock($quantity, 'in', $userId, "Transfer from {$this->stockTransfer->fromWarehouse->name}");
    }

    // Static methods
    public static function getItemsByTransfer(int $transferId): \Illuminate\Database\Eloquent\Collection
    {
        return static::byTransfer($transferId)
            ->with(['product', 'unit', 'fromZone', 'toZone'])
            ->orderBy('line_number')
            ->get();
    }

    public static function getPendingItems(): \Illuminate\Database\Eloquent\Collection
    {
        return static::pending()
            ->with(['stockTransfer', 'product', 'unit'])
            ->get();
    }

    public static function getDamagedItems(): \Illuminate\Database\Eloquent\Collection
    {
        return static::damaged()
            ->with(['stockTransfer', 'product', 'unit'])
            ->orderBy('received_at', 'desc')
            ->get();
    }

    public static function getItemsWithVariance(): \Illuminate\Database\Eloquent\Collection
    {
        return static::withVariance()
            ->with(['stockTransfer', 'product', 'unit'])
            ->orderBy('variance_quantity', 'desc')
            ->get();
    }
}
