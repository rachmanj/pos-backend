<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'purchase_order_id',
        'received_by',
        'receipt_date',
        'status',
        'notes',
        'quality_check_notes',
        'stock_updated',
    ];

    protected $casts = [
        'receipt_date' => 'date',
        'stock_updated' => 'boolean',
    ];

    // Relationships
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReceiptItem::class);
    }

    // Scopes
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'quality_check_pending']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereIn('status', ['complete', 'approved']);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('receipt_number', 'like', "%{$search}%")
                ->orWhereHas('purchaseOrder', function ($sq) use ($search) {
                    $sq->where('po_number', 'like', "%{$search}%");
                });
        });
    }

    // Accessors
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'partial' => 'warning',
            'complete' => 'primary',
            'quality_check_pending' => 'warning',
            'quality_check_failed' => 'destructive',
            'approved' => 'success',
            default => 'secondary',
        };
    }

    public function getFormattedReceiptDateAttribute(): string
    {
        return $this->receipt_date->format('d/m/Y');
    }

    // Helper methods
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'partial']) && !$this->stock_updated;
    }

    public function canUpdateStock(): bool
    {
        return $this->status === 'approved' && !$this->stock_updated;
    }

    public function isComplete(): bool
    {
        return in_array($this->status, ['complete', 'approved']);
    }

    public function updateStock(): bool
    {
        if (!$this->canUpdateStock()) {
            return false;
        }

        DB::transaction(function () {
            foreach ($this->items as $item) {
                if ($item->quantity_accepted > 0) {
                    // Update product stock
                    $productStock = ProductStock::firstOrCreate([
                        'product_id' => $item->product_id,
                        'unit_id' => $item->unit_id,
                    ], [
                        'quantity' => 0,
                    ]);

                    $productStock->increment('quantity', $item->quantity_accepted);

                    // Create stock movement record
                    StockMovement::create([
                        'product_id' => $item->product_id,
                        'unit_id' => $item->unit_id,
                        'type' => 'in',
                        'quantity' => $item->quantity_accepted,
                        'reference_type' => 'purchase_receipt',
                        'reference_id' => $this->id,
                        'notes' => "Stock in from PO {$this->purchaseOrder->po_number}",
                        'user_id' => $this->received_by,
                    ]);
                }

                // Update purchase order item received quantity
                $item->purchaseOrderItem->updateQuantityReceived();
            }

            // Update purchase order status
            $this->updatePurchaseOrderStatus();

            $this->update(['stock_updated' => true]);
        });

        return true;
    }

    public function updatePurchaseOrderStatus(): void
    {
        $purchaseOrder = $this->purchaseOrder;
        $allItemsReceived = $purchaseOrder->items->every(function ($item) {
            return $item->is_fully_received;
        });

        if ($allItemsReceived) {
            $purchaseOrder->update(['status' => 'fully_received']);
        } else {
            $purchaseOrder->update(['status' => 'partially_received']);
        }
    }

    public function approve(): bool
    {
        if ($this->status !== 'complete') {
            return false;
        }

        $this->update(['status' => 'approved']);
        return $this->updateStock();
    }

    // Static methods
    public static function generateReceiptNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "GR{$date}";

        $lastReceipt = static::where('receipt_number', 'like', "{$prefix}%")
            ->orderBy('receipt_number', 'desc')
            ->first();

        if (!$lastReceipt) {
            return "{$prefix}001";
        }

        $lastNumber = (int) substr($lastReceipt->receipt_number, -3);
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$newNumber}";
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->receipt_number) {
                $model->receipt_number = static::generateReceiptNumber();
            }
        });
    }
}
