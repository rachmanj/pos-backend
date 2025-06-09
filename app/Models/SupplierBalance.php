<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SupplierBalance extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'total_outstanding',
        'total_paid',
        'credit_limit',
        'last_payment_date',
        'advance_balance',
        'payment_status',
    ];

    protected $casts = [
        'total_outstanding' => 'decimal:2',
        'total_paid' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'advance_balance' => 'decimal:2',
        'last_payment_date' => 'date',
    ];

    // Relationships
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    // Accessors
    public function getFormattedOutstandingAttribute(): string
    {
        return 'Rp ' . number_format($this->total_outstanding, 0, ',', '.');
    }

    public function getFormattedPaidAttribute(): string
    {
        return 'Rp ' . number_format($this->total_paid, 0, ',', '.');
    }

    public function getFormattedCreditLimitAttribute(): string
    {
        return 'Rp ' . number_format($this->credit_limit, 0, ',', '.');
    }

    public function getFormattedAdvanceBalanceAttribute(): string
    {
        return 'Rp ' . number_format($this->advance_balance, 0, ',', '.');
    }

    public function getAvailableCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->total_outstanding);
    }

    public function getFormattedAvailableCreditAttribute(): string
    {
        return 'Rp ' . number_format($this->available_credit, 0, ',', '.');
    }

    public function getCreditUtilizationPercentageAttribute(): float
    {
        if ($this->credit_limit > 0) {
            return ($this->total_outstanding / $this->credit_limit) * 100;
        }
        return 0;
    }

    public function getPaymentStatusBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            'current' => 'success',
            'overdue' => 'destructive',
            'blocked' => 'destructive',
            default => 'secondary',
        };
    }

    public function getDaysWithoutPaymentAttribute(): ?int
    {
        return $this->last_payment_date ?
            $this->last_payment_date->diffInDays(now()) : null;
    }

    // Business Logic Methods
    public function updateBalance(): void
    {
        // Calculate total outstanding from unpaid/partial purchase orders
        $totalOutstanding = $this->supplier->purchaseOrders()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('outstanding_amount');

        // Calculate total paid from completed payments
        $totalPaid = $this->supplier->purchasePayments()
            ->where('status', 'completed')
            ->sum('amount');

        // Calculate advance balance from advance payments
        $advanceBalance = $this->supplier->purchasePayments()
            ->where('status', 'completed')
            ->where('payment_type', 'advance')
            ->sum('amount');

        // Get last payment date
        $lastPayment = $this->supplier->purchasePayments()
            ->where('status', 'completed')
            ->latest('payment_date')
            ->first();

        // Determine payment status
        $paymentStatus = $this->determinePaymentStatus($totalOutstanding);

        $this->update([
            'total_outstanding' => $totalOutstanding,
            'total_paid' => $totalPaid,
            'advance_balance' => $advanceBalance,
            'last_payment_date' => $lastPayment?->payment_date,
            'payment_status' => $paymentStatus,
        ]);
    }

    protected function determinePaymentStatus(float $outstanding): string
    {
        if ($outstanding == 0) {
            return 'current';
        }

        // Check for overdue orders
        $overdueOrders = $this->supplier->purchaseOrders()
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->exists();

        if ($overdueOrders) {
            return 'overdue';
        }

        // Check credit limit
        if ($this->credit_limit > 0 && $outstanding > $this->credit_limit) {
            return 'blocked';
        }

        return 'current';
    }

    public function canMakeNewPurchase(float $amount): bool
    {
        if ($this->payment_status === 'blocked') {
            return false;
        }

        if ($this->credit_limit > 0) {
            return ($this->total_outstanding + $amount) <= $this->credit_limit;
        }

        return true;
    }

    // Scopes
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('payment_status', 'overdue');
    }

    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('payment_status', 'blocked');
    }

    public function scopeWithOutstanding(Builder $query): Builder
    {
        return $query->where('total_outstanding', '>', 0);
    }

    public function scopeWithAdvanceBalance(Builder $query): Builder
    {
        return $query->where('advance_balance', '>', 0);
    }

    // Static methods
    public static function getTotalOutstandingAmount(): float
    {
        return static::sum('total_outstanding');
    }

    public static function getTotalAdvanceBalance(): float
    {
        return static::sum('advance_balance');
    }

    public static function getOverdueCount(): int
    {
        return static::overdue()->count();
    }

    public static function getBlockedCount(): int
    {
        return static::blocked()->count();
    }
}
