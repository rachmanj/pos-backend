<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_order_id',
        'product_id',
        'quantity_ordered',
        'quantity_delivered',
        'quantity_remaining',
        'unit_price',
        'discount_amount',
        'tax_rate',
        'line_total',
        'delivery_status',
        'notes'
    ];

    protected $casts = [
        'quantity_ordered' => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_remaining' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_total' => 'decimal:2'
    ];

    protected $appends = [
        'delivery_status_label',
        'delivery_percentage',
        'remaining_percentage',
        'is_fully_delivered',
        'can_be_delivered'
    ];

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_PARTIAL = 'partial';
    const STATUS_COMPLETE = 'complete';

    /**
     * Boot method to handle automatic calculations
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->calculateTotals();
            $model->quantity_remaining = $model->quantity_ordered;
        });

        static::updating(function ($model) {
            if ($model->isDirty(['quantity_ordered', 'unit_price', 'discount_amount', 'tax_rate'])) {
                $model->calculateTotals();
            }

            if ($model->isDirty(['quantity_ordered', 'quantity_delivered'])) {
                $model->updateDeliveryStatus();
            }
        });
    }

    /**
     * Relationships
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveryOrderItems(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }

    /**
     * Accessors
     */
    public function getDeliveryStatusLabelAttribute(): string
    {
        return match ($this->delivery_status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PARTIAL => 'Partial',
            self::STATUS_COMPLETE => 'Complete',
            default => 'Unknown'
        };
    }

    public function getDeliveryPercentageAttribute(): float
    {
        if ($this->quantity_ordered == 0) return 0;
        return round(($this->quantity_delivered / $this->quantity_ordered) * 100, 2);
    }

    public function getRemainingPercentageAttribute(): float
    {
        return 100 - $this->delivery_percentage;
    }

    public function getIsFullyDeliveredAttribute(): bool
    {
        return $this->delivery_status === self::STATUS_COMPLETE;
    }

    public function getCanBeDeliveredAttribute(): bool
    {
        return $this->quantity_remaining > 0;
    }

    /**
     * Business Logic: Calculate line totals
     */
    private function calculateTotals(): void
    {
        $subtotal = $this->quantity_ordered * $this->unit_price - $this->discount_amount;
        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $this->line_total = $subtotal + $taxAmount;
    }

    /**
     * Business Logic: Update delivery status based on quantities
     */
    private function updateDeliveryStatus(): void
    {
        $this->quantity_remaining = $this->quantity_ordered - $this->quantity_delivered;

        if ($this->quantity_delivered == 0) {
            $this->delivery_status = self::STATUS_PENDING;
        } elseif ($this->quantity_delivered >= $this->quantity_ordered) {
            $this->delivery_status = self::STATUS_COMPLETE;
            $this->quantity_remaining = 0;
        } else {
            $this->delivery_status = self::STATUS_PARTIAL;
        }
    }

    /**
     * Business Logic: Add delivery quantity
     */
    public function addDelivery(float $quantity): bool
    {
        if ($quantity <= 0 || $this->quantity_delivered + $quantity > $this->quantity_ordered) {
            return false;
        }

        $this->quantity_delivered += $quantity;
        $this->updateDeliveryStatus();
        return $this->save();
    }

    /**
     * Scope: Filter by delivery status
     */
    public function scopeWithDeliveryStatus($query, string $status)
    {
        return $query->where('delivery_status', $status);
    }

    /**
     * Scope: Pending delivery
     */
    public function scopePendingDelivery($query)
    {
        return $query->where('delivery_status', '!=', self::STATUS_COMPLETE);
    }
}
