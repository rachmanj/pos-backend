<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CustomerPaymentReceive extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payment_number',
        'reference_number',
        'customer_id',
        'warehouse_id',
        'payment_date',
        'total_amount',
        'allocated_amount',
        'unallocated_amount',
        'payment_method_id',
        'payment_reference',
        'payment_details',
        'status',
        'allocation_status',
        'received_by',
        'verified_by',
        'approved_by',
        'verified_at',
        'approved_at',
        'notes',
        'internal_notes',
        'metadata',
        'is_reconciled',
        'reconciled_date',
        'bank_statement_reference',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'verified_at' => 'datetime',
        'approved_at' => 'datetime',
        'reconciled_date' => 'date',
        'total_amount' => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'unallocated_amount' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'payment_details' => 'array',
        'metadata' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function appliedAllocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class)->where('status', 'applied');
    }

    // Accessors
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->verified_at);
    }

    public function getIsApprovedAttribute(): bool
    {
        return !is_null($this->approved_at);
    }

    public function getIsFullyAllocatedAttribute(): bool
    {
        return $this->allocation_status === 'fully_allocated';
    }

    public function getIsPartiallyAllocatedAttribute(): bool
    {
        return $this->allocation_status === 'partially_allocated';
    }

    public function getIsUnallocatedAttribute(): bool
    {
        return $this->allocation_status === 'unallocated';
    }

    public function getCanAllocateAttribute(): bool
    {
        return $this->status === 'verified' && $this->unallocated_amount > 0;
    }

    public function getCanReverseAttribute(): bool
    {
        return in_array($this->status, ['verified', 'allocated', 'completed']) && $this->allocated_amount > 0;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'yellow',
            'verified' => 'blue',
            'allocated' => 'green',
            'completed' => 'emerald',
            'cancelled' => 'red',
            default => 'gray'
        };
    }

    public function getAllocationStatusColorAttribute(): string
    {
        return match ($this->allocation_status) {
            'unallocated' => 'red',
            'partially_allocated' => 'yellow',
            'fully_allocated' => 'green',
            default => 'gray'
        };
    }

    public function getDaysOldAttribute(): int
    {
        return $this->payment_date->diffInDays(now());
    }

    // Business Logic Methods
    public function generatePaymentNumber(): string
    {
        $warehouseCode = $this->warehouse?->code ?? 'WH';
        $date = $this->payment_date->format('Ymd');
        $sequence = static::whereDate('payment_date', $this->payment_date)
            ->where('warehouse_id', $this->warehouse_id)
            ->count() + 1;

        return "PAY{$warehouseCode}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function verify(int $verifiedBy): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update([
            'status' => 'verified',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
        ]);

        return true;
    }

    public function approve(int $approvedBy): bool
    {
        if ($this->status !== 'verified') {
            return false;
        }

        $this->update([
            'status' => 'allocated',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
        ]);

        return true;
    }

    public function allocateToSale(Sale $sale, float $amount, int $allocatedBy, string $notes = ''): ?CustomerPaymentAllocation
    {
        if (!$this->can_allocate || $amount > $this->unallocated_amount) {
            return null;
        }

        $allocation = $this->allocations()->create([
            'sale_id' => $sale->id,
            'customer_id' => $this->customer_id,
            'allocated_amount' => $amount,
            'allocation_date' => now()->toDateString(),
            'allocation_type' => 'manual',
            'status' => 'applied',
            'allocated_by' => $allocatedBy,
            'applied_at' => now(),
            'notes' => $notes,
        ]);

        $this->updateAllocationAmounts();
        $sale->updatePaymentStatus();

        return $allocation;
    }

    public function autoAllocatePayments(int $allocatedBy): array
    {
        $allocations = [];
        $remainingAmount = $this->unallocated_amount;

        // Get outstanding sales for this customer, oldest first
        $outstandingSales = Sale::where('customer_id', $this->customer_id)
            ->where('payment_status', '!=', 'paid')
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->orderBy('sale_date', 'asc')
            ->get();

        foreach ($outstandingSales as $sale) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocationAmount = min($remainingAmount, $sale->outstanding_amount);

            $allocation = $this->allocateToSale($sale, $allocationAmount, $allocatedBy, 'Auto-allocated payment');

            if ($allocation) {
                $allocations[] = $allocation;
                $remainingAmount -= $allocationAmount;
            }
        }

        return $allocations;
    }

    public function updateAllocationAmounts(): void
    {
        $allocatedAmount = $this->appliedAllocations()->sum('allocated_amount');
        $unallocatedAmount = $this->total_amount - $allocatedAmount;

        $allocationStatus = match (true) {
            $allocatedAmount == 0 => 'unallocated',
            $unallocatedAmount == 0 => 'fully_allocated',
            default => 'partially_allocated'
        };

        $status = match ($allocationStatus) {
            'fully_allocated' => 'completed',
            'partially_allocated' => 'allocated',
            default => $this->status
        };

        $this->update([
            'allocated_amount' => $allocatedAmount,
            'unallocated_amount' => $unallocatedAmount,
            'allocation_status' => $allocationStatus,
            'status' => $status,
        ]);
    }

    public function reverseAllocation(CustomerPaymentAllocation $allocation, int $reversedBy, string $reason = ''): bool
    {
        if ($allocation->customer_payment_receive_id !== $this->id || $allocation->status !== 'applied') {
            return false;
        }

        $allocation->update([
            'status' => 'reversed',
            'reversed_at' => now(),
            'reversal_reason' => $reason,
        ]);

        $this->updateAllocationAmounts();
        $allocation->sale->updatePaymentStatus();

        return true;
    }

    public function reconcile(string $bankReference, int $reconciledBy): bool
    {
        if ($this->is_reconciled) {
            return false;
        }

        $this->update([
            'is_reconciled' => true,
            'reconciled_date' => now()->toDateString(),
            'bank_statement_reference' => $bankReference,
        ]);

        return true;
    }

    // Query Scopes
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('status', 'verified');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeUnallocated(Builder $query): Builder
    {
        return $query->where('allocation_status', 'unallocated');
    }

    public function scopePartiallyAllocated(Builder $query): Builder
    {
        return $query->where('allocation_status', 'partially_allocated');
    }

    public function scopeFullyAllocated(Builder $query): Builder
    {
        return $query->where('allocation_status', 'fully_allocated');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForWarehouse(Builder $query, int $warehouseId): Builder
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeReconciled(Builder $query): Builder
    {
        return $query->where('is_reconciled', true);
    }

    public function scopeUnreconciled(Builder $query): Builder
    {
        return $query->where('is_reconciled', false);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('payment_number', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhere('payment_reference', 'like', "%{$search}%")
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

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = $payment->generatePaymentNumber();
            }

            if (empty($payment->unallocated_amount)) {
                $payment->unallocated_amount = $payment->total_amount;
            }
        });

        static::created(function ($payment) {
            // Update customer AR balance
            $payment->customer->updateArBalance();
        });

        static::updated(function ($payment) {
            // Update customer AR balance when payment is modified
            $payment->customer->updateArBalance();
        });
    }
}
