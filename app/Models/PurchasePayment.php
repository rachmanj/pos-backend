<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PurchasePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'supplier_id',
        'purchase_order_id',
        'payment_method_id',
        'payment_date',
        'amount',
        'reference_number',
        'status',
        'payment_type',
        'notes',
        'processed_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'completed' => 'success',
            'cancelled' => 'destructive',
            'failed' => 'destructive',
            default => 'secondary',
        };
    }

    public function getPaymentTypeBadgeAttribute(): string
    {
        return match ($this->payment_type) {
            'advance' => 'info',
            'partial' => 'warning',
            'full' => 'success',
            'overpayment' => 'secondary',
            default => 'secondary',
        };
    }

    public function getIsApprovedAttribute(): bool
    {
        return !is_null($this->approved_by) && !is_null($this->approved_at);
    }

    public function getCanBeEditedAttribute(): bool
    {
        return $this->status === 'pending';
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return in_array($this->status, ['pending']);
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return $this->status === 'pending' && !$this->is_approved;
    }

    // Business Logic Methods
    public function generatePaymentNumber(): string
    {
        $date = now()->format('Ymd');
        $sequence = static::whereDate('payment_date', today())->count() + 1;
        return "PP{$date}" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public function approve(User $approver): bool
    {
        if (!$this->can_be_approved) {
            return false;
        }

        $this->update([
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return true;
    }

    public function complete(): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }

        $this->update(['status' => 'completed']);

        // Update supplier balance
        $this->supplier->updateBalance();

        // Update purchase order payment status if linked
        if ($this->purchase_order_id) {
            $this->purchaseOrder->updatePaymentStatus();
        }

        return true;
    }

    public function cancel(): bool
    {
        if (!$this->can_be_cancelled) {
            return false;
        }

        $this->update(['status' => 'cancelled']);

        // Update supplier balance
        $this->supplier->updateBalance();

        // Update purchase order payment status if linked
        if ($this->purchase_order_id) {
            $this->purchaseOrder->updatePaymentStatus();
        }

        return true;
    }

    public function allocateToOrders(array $allocations): void
    {
        // Delete existing allocations
        $this->allocations()->delete();

        // Create new allocations
        foreach ($allocations as $allocation) {
            $this->allocations()->create([
                'purchase_order_id' => $allocation['purchase_order_id'],
                'allocated_amount' => $allocation['allocated_amount'],
                'notes' => $allocation['notes'] ?? null,
            ]);
        }

        // Update payment type based on allocations
        $this->updatePaymentType();
    }

    protected function updatePaymentType(): void
    {
        $totalAllocated = $this->allocations->sum('allocated_amount');

        if ($totalAllocated == 0) {
            $type = 'advance';
        } elseif ($totalAllocated < $this->amount) {
            $type = 'overpayment';
        } elseif ($totalAllocated == $this->amount) {
            $type = 'full';
        } else {
            $type = 'partial';
        }

        $this->update(['payment_type' => $type]);
    }

    // Scopes
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('payment_date', [$startDate, $endDate]);
    }

    public function scopeByPaymentMethod(Builder $query, int $paymentMethodId): Builder
    {
        return $query->where('payment_method_id', $paymentMethodId);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('payment_number', 'like', "%{$search}%")
                ->orWhere('reference_number', 'like', "%{$search}%")
                ->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                });
        });
    }

    // Static methods
    public static function getTotalPaidToSupplier(int $supplierId, ?string $startDate = null, ?string $endDate = null): float
    {
        $query = static::completed()->forSupplier($supplierId);

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        return $query->sum('amount');
    }

    public static function getPaymentsByMonth(int $year): array
    {
        return static::completed()
            ->whereYear('payment_date', $year)
            ->selectRaw('MONTH(payment_date) as month, SUM(amount) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('total', 'month')
            ->toArray();
    }

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->payment_number)) {
                $payment->payment_number = $payment->generatePaymentNumber();
            }
        });
    }
}
