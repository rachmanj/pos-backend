<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'unit_id',
        'quantity_ordered',
        'quantity_received',
        'unit_price',
        'total_price',
        'notes',
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_received' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    // Accessors
    public function getFormattedUnitPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->unit_price, 0, ',', '.');
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->total_price, 0, ',', '.');
    }

    public function getRemainingQuantityAttribute(): float
    {
        return $this->quantity_ordered - $this->quantity_received;
    }

    public function getReceiptPercentageAttribute(): float
    {
        if ($this->quantity_ordered == 0) {
            return 0;
        }

        return ($this->quantity_received / $this->quantity_ordered) * 100;
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->quantity_received >= $this->quantity_ordered;
    }

    // Helper methods
    public function calculateTotalPrice(): void
    {
        $this->total_price = $this->quantity_ordered * $this->unit_price;
        $this->save();
    }

    public function updateQuantityReceived(): void
    {
        $totalReceived = $this->receiptItems()
            ->whereHas('purchaseReceipt', function ($query) {
                $query->where('stock_updated', true);
            })
            ->sum('quantity_accepted');

        $this->update(['quantity_received' => $totalReceived]);
    }

    public function canReceiveQuantity(float $quantity): bool
    {
        return ($this->quantity_received + $quantity) <= $this->quantity_ordered;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->total_price = $model->quantity_ordered * $model->unit_price;
        });

        static::saved(function ($model) {
            // Recalculate purchase order totals when item is saved
            $model->purchaseOrder?->calculateTotals();
        });

        static::deleted(function ($model) {
            // Recalculate purchase order totals when item is deleted
            $model->purchaseOrder?->calculateTotals();
        });
    }
}
