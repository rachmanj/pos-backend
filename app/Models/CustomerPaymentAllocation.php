<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CustomerPaymentAllocation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_payment_receive_id',
        'sale_id',
        'customer_id',
        'allocated_amount',
        'allocation_date',
        'allocation_type',
        'status',
        'applied_at',
        'reversed_at',
        'allocated_by',
        'approved_by',
        'approved_at',
        'notes',
        'reversal_reason',
        'metadata',
    ];

    protected $casts = [
        'allocation_date' => 'date',
        'applied_at' => 'datetime',
        'reversed_at' => 'datetime',
        'approved_at' => 'datetime',
        'allocated_amount' => 'decimal:2',
        'metadata' => 'array',
    ];

    // Relationships
    public function customerPaymentReceive(): BelongsTo
    {
        return $this->belongsTo(CustomerPaymentReceive::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'allocated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors
    public function getIsAppliedAttribute(): bool
    {
        return $this->status === 'applied';
    }

    public function getIsReversedAttribute(): bool
    {
        return $this->status === 'reversed';
    }

    public function getIsPendingAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getCanReverseAttribute(): bool
    {
        return $this->status === 'applied';
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'applied' => 'green',
            'reversed' => 'red',
            'cancelled' => 'gray',
            default => 'gray'
        };
    }

    public function getAllocationTypeColorAttribute(): string
    {
        return match ($this->allocation_type) {
            'automatic' => 'blue',
            'manual' => 'green',
            'partial' => 'yellow',
            'overpayment' => 'purple',
            'advance' => 'indigo',
            default => 'gray'
        };
    }

    public function getDaysOldAttribute(): int
    {
        return $this->allocation_date->diffInDays(now());
    }

    // Business Logic Methods
    public function apply(int $approvedBy = null): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $updateData = [
            'status' => 'applied',
            'applied_at' => now(),
        ];

        if ($approvedBy) {
            $updateData['approved_by'] = $approvedBy;
            $updateData['approved_at'] = now();
        }

        $this->update($updateData);

        // Update related records
        $this->customerPaymentReceive->updateAllocationAmounts();
        $this->sale->updatePaymentStatus();

        return true;
    }

    public function reverse(int $reversedBy, string $reason = ''): bool
    {
        if ($this->status !== 'applied') {
            return false;
        }

        $this->update([
            'status' => 'reversed',
            'reversed_at' => now(),
            'reversal_reason' => $reason,
        ]);

        // Update related records
        $this->customerPaymentReceive->updateAllocationAmounts();
        $this->sale->updatePaymentStatus();

        return true;
    }

    public function cancel(string $reason = ''): bool
    {
        if (!in_array($this->status, ['pending', 'applied'])) {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'reversal_reason' => $reason,
        ]);

        // Update related records if it was applied
        if ($this->status === 'applied') {
            $this->customerPaymentReceive->updateAllocationAmounts();
            $this->sale->updatePaymentStatus();
        }

        return true;
    }

    // Query Scopes
    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', 'applied');
    }

    public function scopeReversed(Builder $query): Builder
    {
        return $query->where('status', 'reversed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForSale(Builder $query, int $saleId): Builder
    {
        return $query->where('sale_id', $saleId);
    }

    public function scopeForPayment(Builder $query, int $paymentId): Builder
    {
        return $query->where('customer_payment_receive_id', $paymentId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('allocation_type', $type);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('allocation_date', [$startDate, $endDate]);
    }

    public function scopeAutomatic(Builder $query): Builder
    {
        return $query->where('allocation_type', 'automatic');
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where('allocation_type', 'manual');
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('notes', 'like', "%{$search}%")
                ->orWhereHas('customerPaymentReceive', function ($paymentQuery) use ($search) {
                    $paymentQuery->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%");
                })
                ->orWhereHas('sale', function ($saleQuery) use ($search) {
                    $saleQuery->where('sale_number', 'like', "%{$search}%")
                        ->orWhere('receipt_number', 'like', "%{$search}%");
                })
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%");
                });
        });
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::created(function ($allocation) {
            // Update customer AR balance
            $allocation->customer->updateArBalance();
        });

        static::updated(function ($allocation) {
            // Update customer AR balance when allocation is modified
            $allocation->customer->updateArBalance();
        });

        static::deleted(function ($allocation) {
            // Update related records when allocation is deleted
            $allocation->customerPaymentReceive->updateAllocationAmounts();
            $allocation->sale->updatePaymentStatus();
            $allocation->customer->updateArBalance();
        });
    }
}
