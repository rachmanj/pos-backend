<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'warehouse_zone_id',
        'unit_id',
        'product_name',
        'product_sku',
        'product_barcode',
        'quantity',
        'unit_price',
        'original_price',
        'cost_price',
        'line_discount_amount',
        'line_discount_percentage',
        'line_tax_amount',
        'line_tax_percentage',
        'line_total',
        'line_subtotal',
        'total_cost',
        'gross_profit',
        'discount_type',
        'discount_reason',
        'available_stock',
        'lot_number',
        'expiry_date',
        'returned_quantity',
        'refunded_amount',
        'is_returnable',
        'notes',
        'metadata',
        'promotion_code',
        'promotion_name',
        'serial_number',
        'requires_serial',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'original_price' => 'decimal:4',
        'cost_price' => 'decimal:4',
        'line_discount_amount' => 'decimal:2',
        'line_discount_percentage' => 'decimal:2',
        'line_tax_amount' => 'decimal:2',
        'line_tax_percentage' => 'decimal:2',
        'line_total' => 'decimal:2',
        'line_subtotal' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'available_stock' => 'decimal:4',
        'returned_quantity' => 'decimal:4',
        'refunded_amount' => 'decimal:2',
        'is_returnable' => 'boolean',
        'requires_serial' => 'boolean',
        'expiry_date' => 'date',
        'metadata' => 'array',
    ];

    // Relationships
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouseZone(): BelongsTo
    {
        return $this->belongsTo(WarehouseZone::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // Accessors (Laravel 11+ style)
    protected function netQuantity(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->quantity - $this->returned_quantity,
        );
    }

    protected function netAmount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->line_total - $this->refunded_amount,
        );
    }

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->line_discount_amount +
                (($this->line_subtotal * $this->line_discount_percentage) / 100),
        );
    }

    protected function finalUnitPrice(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->quantity > 0 ? $this->line_total / $this->quantity : 0,
        );
    }

    protected function profitMargin(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->line_total > 0 ?
                ($this->gross_profit / $this->line_total) * 100 : 0,
        );
    }

    protected function isPartiallyReturned(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->returned_quantity > 0 && $this->returned_quantity < $this->quantity,
        );
    }

    protected function isFullyReturned(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->returned_quantity >= $this->quantity,
        );
    }

    protected function canReturn(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->is_returnable &&
                $this->returned_quantity < $this->quantity &&
                $this->sale->status === 'completed',
        );
    }

    protected function remainingReturnQuantity(): Attribute
    {
        return Attribute::make(
            get: fn() => max(0, $this->quantity - $this->returned_quantity),
        );
    }

    protected function remainingReturnAmount(): Attribute
    {
        return Attribute::make(
            get: fn() => max(0, $this->line_total - $this->refunded_amount),
        );
    }

    // Business Logic Methods
    public function calculateLineTotals(): void
    {
        $subtotal = $this->quantity * $this->unit_price;

        // Apply line discount
        $discountAmount = $this->line_discount_amount;
        if ($this->line_discount_percentage > 0) {
            $discountAmount += ($subtotal * $this->line_discount_percentage) / 100;
        }

        $subtotalAfterDiscount = $subtotal - $discountAmount;

        // Apply tax
        $taxAmount = ($subtotalAfterDiscount * $this->line_tax_percentage) / 100;

        $total = $subtotalAfterDiscount + $taxAmount;

        // Calculate costs and profit
        $totalCost = $this->quantity * $this->cost_price;
        $grossProfit = $total - $totalCost;

        // Use updateQuietly to avoid triggering events and prevent infinite loops
        $this->updateQuietly([
            'line_subtotal' => $subtotal,
            'line_discount_amount' => $discountAmount,
            'line_tax_amount' => $taxAmount,
            'line_total' => $total,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
        ]);
    }

    public function applyDiscount(float $percentage = 0, float $amount = 0, ?string $type = null, ?string $reason = null): void
    {
        $this->update([
            'line_discount_percentage' => $percentage,
            'line_discount_amount' => $amount,
            'discount_type' => $type,
            'discount_reason' => $reason,
        ]);

        $this->calculateLineTotals();
    }

    public function applyTax(float $percentage): void
    {
        $this->update(['line_tax_percentage' => $percentage]);
        $this->calculateLineTotals();
    }

    public function processReturn(float $quantity, string $reason = ''): bool
    {
        if ($quantity > $this->remaining_return_quantity) {
            return false;
        }

        $returnRatio = $quantity / $this->quantity;
        $refundAmount = $this->line_total * $returnRatio;

        $this->increment('returned_quantity', $quantity);
        $this->increment('refunded_amount', $refundAmount);

        // Update sale totals
        $this->sale->calculateTotals();

        // Return stock to warehouse
        $this->returnToStock($quantity);

        return true;
    }

    protected function returnToStock(float $quantity): void
    {
        // Add back to warehouse stock
        $warehouseStock = \App\Models\WarehouseStock::where('warehouse_id', $this->sale->warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        if ($warehouseStock) {
            $warehouseStock->increment('quantity', $quantity);
            $warehouseStock->increment('available_quantity', $quantity);
        }

        // Create stock movement
        \App\Models\StockMovement::create([
            'product_id' => $this->product_id,
            'warehouse_id' => $this->sale->warehouse_id,
            'movement_type' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $this->cost_price,
            'reference_type' => 'return',
            'reference_id' => $this->sale->id,
            'notes' => "Return from sale: {$this->sale->sale_number}",
            'user_id' => \Illuminate\Support\Facades\Auth::id(),
        ]);
    }

    public function updateQuantity(float $newQuantity): void
    {
        if ($newQuantity <= 0) {
            $this->delete();
            return;
        }

        $this->update(['quantity' => $newQuantity]);
        $this->calculateLineTotals();
        $this->sale->calculateTotals();
    }

    public function updatePrice(float $newPrice): void
    {
        $this->update(['unit_price' => $newPrice]);
        $this->calculateLineTotals();
        $this->sale->calculateTotals();
    }

    public function checkStockAvailability(): bool
    {
        $warehouseStock = \App\Models\WarehouseStock::where('warehouse_id', $this->sale->warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        return $warehouseStock && $warehouseStock->available_quantity >= $this->quantity;
    }

    public function getStockShortage(): float
    {
        $warehouseStock = \App\Models\WarehouseStock::where('warehouse_id', $this->sale->warehouse_id)
            ->where('product_id', $this->product_id)
            ->first();

        $availableStock = $warehouseStock?->available_quantity ?? 0;
        return max(0, $this->quantity - $availableStock);
    }

    // Scopes
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeReturnable(Builder $query): Builder
    {
        return $query->where('is_returnable', true)
            ->whereColumn('returned_quantity', '<', 'quantity');
    }

    public function scopeWithReturns(Builder $query): Builder
    {
        return $query->where('returned_quantity', '>', 0);
    }

    public function scopeWithDiscounts(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('line_discount_amount', '>', 0)
                ->orWhere('line_discount_percentage', '>', 0);
        });
    }

    public function scopeWithPromotions(Builder $query): Builder
    {
        return $query->whereNotNull('promotion_code');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', now()->addDays($days));
    }

    public function scopeByLot(Builder $query, string $lotNumber): Builder
    {
        return $query->where('lot_number', $lotNumber);
    }

    public function scopeWithSerial(Builder $query): Builder
    {
        return $query->whereNotNull('serial_number');
    }

    // Events (Laravel 11+ style)
    protected static function booted(): void
    {
        static::creating(function ($saleItem) {
            $saleItem->calculateLineTotals();
        });

        static::updating(function ($saleItem) {
            if ($saleItem->isDirty(['quantity', 'unit_price', 'line_discount_percentage', 'line_discount_amount', 'line_tax_percentage'])) {
                $saleItem->calculateLineTotals();
            }
        });

        static::saved(function ($saleItem) {
            // Recalculate sale totals when item changes
            $saleItem->sale->calculateTotals();
        });

        static::deleted(function ($saleItem) {
            // Recalculate sale totals when item is deleted
            $saleItem->sale->calculateTotals();
        });
    }
}
