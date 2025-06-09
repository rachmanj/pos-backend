<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_payment_id',
        'purchase_order_id',
        'allocated_amount',
        'notes',
    ];

    protected $casts = [
        'allocated_amount' => 'decimal:2',
    ];

    // Relationships
    public function purchasePayment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->allocated_amount, 0, ',', '.');
    }

    // Business Logic Methods
    public function getPercentageOfPaymentAttribute(): float
    {
        if ($this->purchasePayment && $this->purchasePayment->amount > 0) {
            return ($this->allocated_amount / $this->purchasePayment->amount) * 100;
        }
        return 0;
    }

    public function getPercentageOfOrderAttribute(): float
    {
        if ($this->purchaseOrder && $this->purchaseOrder->outstanding_amount > 0) {
            return ($this->allocated_amount / $this->purchaseOrder->outstanding_amount) * 100;
        }
        return 0;
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::created(function ($allocation) {
            // Update purchase order payment status when allocation is created
            $allocation->purchaseOrder->updatePaymentStatus();
        });

        static::updated(function ($allocation) {
            // Update purchase order payment status when allocation is updated
            $allocation->purchaseOrder->updatePaymentStatus();
        });

        static::deleted(function ($allocation) {
            // Update purchase order payment status when allocation is deleted
            $allocation->purchaseOrder->updatePaymentStatus();
        });
    }
}
