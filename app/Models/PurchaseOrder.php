<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class PurchaseOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'po_number',
        'supplier_id',
        'created_by',
        'approved_by',
        'status',
        'order_date',
        'expected_delivery_date',
        'approved_date',
        'subtotal',
        'tax_amount',
        'total_amount',
        'paid_amount',
        'outstanding_amount',
        'payment_status',
        'payment_terms',
        'due_date',
        'notes',
        'terms_conditions',
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'approved_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(PurchaseReceipt::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PurchasePayment::class);
    }

    // Scopes
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['draft', 'pending_approval']);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['cancelled']);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('po_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
        });
    }

    // Accessors
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'secondary',
            'pending_approval' => 'warning',
            'approved' => 'success',
            'sent_to_supplier' => 'info',
            'partially_received' => 'primary',
            'fully_received' => 'success',
            'cancelled' => 'destructive',
            default => 'secondary',
        };
    }

    public function getFormattedTotalAttribute(): string
    {
        return 'Rp ' . number_format($this->total_amount, 0, ',', '.');
    }

    public function getFormattedSubtotalAttribute(): string
    {
        return 'Rp ' . number_format($this->subtotal, 0, ',', '.');
    }

    public function getFormattedTaxAttribute(): string
    {
        return 'Rp ' . number_format($this->tax_amount, 0, ',', '.');
    }

    public function getFormattedPaidAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->paid_amount, 0, ',', '.');
    }

    public function getFormattedOutstandingAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->outstanding_amount, 0, ',', '.');
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            'unpaid' => 'destructive',
            'partial' => 'warning',
            'paid' => 'success',
            'overpaid' => 'secondary',
            default => 'secondary',
        };
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date && $this->due_date->isPast() && $this->outstanding_amount > 0;
    }

    public function getDaysOverdueAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }
        return $this->due_date->diffInDays(now());
    }

    // Helper methods
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft']);
    }

    public function canBeApproved(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canBeCancelled(): bool
    {
        return !in_array($this->status, ['fully_received', 'cancelled']);
    }

    public function canBeDeleted(): bool
    {
        return $this->status === 'draft';
    }

    public function isFullyReceived(): bool
    {
        return $this->status === 'fully_received';
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('total_price');
        $this->tax_amount = $this->subtotal * 0.11; // 11% PPN
        $this->total_amount = $this->subtotal + $this->tax_amount;

        // Initialize payment fields if not set
        if (is_null($this->outstanding_amount)) {
            $this->outstanding_amount = $this->total_amount;
            $this->paid_amount = 0;
            $this->payment_status = 'unpaid';
        }

        // Set due date based on supplier payment terms
        if (is_null($this->due_date) && $this->supplier) {
            $this->due_date = $this->supplier->getDueDateForNewOrder();
            $this->payment_terms = $this->supplier->payment_terms . ' days';
        }

        $this->save();
    }

    public function updatePaymentStatus(): void
    {
        // Calculate total paid amount from allocations
        $totalPaid = $this->paymentAllocations()
            ->whereHas('purchasePayment', function ($query) {
                $query->where('status', 'completed');
            })
            ->sum('allocated_amount');

        $this->paid_amount = $totalPaid;
        $this->outstanding_amount = $this->total_amount - $totalPaid;

        // Determine payment status
        if ($totalPaid == 0) {
            $this->payment_status = 'unpaid';
        } elseif ($totalPaid < $this->total_amount) {
            $this->payment_status = 'partial';
        } elseif ($totalPaid == $this->total_amount) {
            $this->payment_status = 'paid';
        } else {
            $this->payment_status = 'overpaid';
        }

        $this->save();

        // Update supplier balance
        $this->supplier->updateBalance();
    }

    public function approve(User $approver): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $this->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_date' => now(),
        ]);

        return true;
    }

    public function cancel(): bool
    {
        if (!$this->canBeCancelled()) {
            return false;
        }

        $this->update(['status' => 'cancelled']);
        return true;
    }

    // Static methods
    public static function generatePoNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "PO{$date}";

        $lastPo = static::where('po_number', 'like', "{$prefix}%")
            ->orderBy('po_number', 'desc')
            ->first();

        if (!$lastPo) {
            return "{$prefix}001";
        }

        $lastNumber = (int) substr($lastPo->po_number, -3);
        $newNumber = str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}{$newNumber}";
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->po_number) {
                $model->po_number = static::generatePoNumber();
            }
        });
    }
}
