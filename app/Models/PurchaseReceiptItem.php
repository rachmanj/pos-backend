<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseReceiptItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_receipt_id',
        'purchase_order_item_id',
        'product_id',
        'unit_id',
        'quantity_received',
        'quantity_accepted',
        'quantity_rejected',
        'quality_status',
        'quality_notes',
        'rejection_reason',
    ];

    protected $casts = [
        'quantity_received' => 'decimal:3',
        'quantity_accepted' => 'decimal:3',
        'quantity_rejected' => 'decimal:3',
    ];

    // Relationships
    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // Accessors
    public function getQualityStatusBadgeAttribute(): string
    {
        return match ($this->quality_status) {
            'pending' => 'warning',
            'passed' => 'success',
            'failed' => 'destructive',
            'partial' => 'secondary',
            default => 'secondary',
        };
    }

    public function getAcceptanceRateAttribute(): float
    {
        if ($this->quantity_received == 0) {
            return 0;
        }

        return ($this->quantity_accepted / $this->quantity_received) * 100;
    }

    public function getRejectionRateAttribute(): float
    {
        if ($this->quantity_received == 0) {
            return 0;
        }

        return ($this->quantity_rejected / $this->quantity_received) * 100;
    }

    // Helper methods
    public function validateQuantities(): bool
    {
        return ($this->quantity_accepted + $this->quantity_rejected) === $this->quantity_received;
    }

    public function updateQualityStatus(): void
    {
        if ($this->quantity_rejected == 0) {
            $this->quality_status = 'passed';
        } elseif ($this->quantity_accepted == 0) {
            $this->quality_status = 'failed';
        } else {
            $this->quality_status = 'partial';
        }

        $this->save();
    }

    public function canBeEdited(): bool
    {
        return $this->purchaseReceipt->canBeEdited();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-update quality status based on quantities
            if ($model->quantity_received > 0) {
                if ($model->quantity_rejected == 0) {
                    $model->quality_status = 'passed';
                } elseif ($model->quantity_accepted == 0) {
                    $model->quality_status = 'failed';
                } else {
                    $model->quality_status = 'partial';
                }
            }
        });

        static::saved(function ($model) {
            // Update purchase receipt status based on all items
            $receipt = $model->purchaseReceipt;
            $allItems = $receipt->items;

            $allPassed = $allItems->every(function ($item) {
                return $item->quality_status === 'passed';
            });

            $anyFailed = $allItems->contains(function ($item) {
                return $item->quality_status === 'failed';
            });

            if ($allPassed) {
                $receipt->update(['status' => 'complete']);
            } elseif ($anyFailed) {
                $receipt->update(['status' => 'quality_check_failed']);
            } else {
                $receipt->update(['status' => 'quality_check_pending']);
            }
        });
    }
}
