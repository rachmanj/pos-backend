<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CustomerPaymentSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'sale_id',
        'schedule_number',
        'schedule_name',
        'description',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'installment_amount',
        'frequency',
        'frequency_days',
        'total_installments',
        'completed_installments',
        'start_date',
        'end_date',
        'next_payment_date',
        'last_payment_date',
        'status',
        'auto_generate_reminders',
        'reminder_days_before',
        'late_fee_percentage',
        'late_fee_amount',
        'grace_period_days',
        'total_late_fees',
        'created_by',
        'approved_by',
        'approved_at',
        'terms_and_conditions',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_payment_date' => 'date',
        'last_payment_date' => 'date',
        'approved_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'late_fee_percentage' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'total_late_fees' => 'decimal:2',
        'frequency_days' => 'integer',
        'total_installments' => 'integer',
        'completed_installments' => 'integer',
        'reminder_days_before' => 'integer',
        'grace_period_days' => 'integer',
        'auto_generate_reminders' => 'boolean',
        'metadata' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->status === 'completed';
    }

    public function getIsSuspendedAttribute(): bool
    {
        return $this->status === 'suspended';
    }

    public function getIsCancelledAttribute(): bool
    {
        return $this->status === 'cancelled';
    }

    public function getIsDefaultedAttribute(): bool
    {
        return $this->status === 'defaulted';
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_installments <= 0) {
            return 0;
        }

        return ($this->completed_installments / $this->total_installments) * 100;
    }

    public function getAmountProgressPercentageAttribute(): float
    {
        if ($this->total_amount <= 0) {
            return 0;
        }

        return ($this->paid_amount / $this->total_amount) * 100;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->next_payment_date && $this->next_payment_date->isPast() && $this->is_active;
    }

    public function getDaysOverdueAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }

        return $this->next_payment_date->diffInDays(now());
    }

    public function getIsInGracePeriodAttribute(): bool
    {
        return $this->is_overdue && $this->days_overdue <= $this->grace_period_days;
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => $this->is_overdue ? 'red' : 'green',
            'completed' => 'emerald',
            'suspended' => 'yellow',
            'cancelled' => 'gray',
            'defaulted' => 'red',
            default => 'gray'
        };
    }

    public function getFrequencyDisplayAttribute(): string
    {
        return match ($this->frequency) {
            'weekly' => 'Weekly',
            'bi_weekly' => 'Bi-weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'custom' => "Every {$this->frequency_days} days",
            default => 'Monthly'
        };
    }

    public function getRemainingInstallmentsAttribute(): int
    {
        return max(0, $this->total_installments - $this->completed_installments);
    }

    public function getEstimatedCompletionDateAttribute(): ?Carbon
    {
        if ($this->remaining_installments <= 0) {
            return null;
        }

        $frequencyDays = $this->getFrequencyInDays();
        return $this->next_payment_date->addDays($frequencyDays * ($this->remaining_installments - 1));
    }

    // Business Logic Methods
    public function generateScheduleNumber(): string
    {
        $customerCode = $this->customer?->customer_code ?? 'CUST';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())
            ->where('customer_id', $this->customer_id)
            ->count() + 1;

        return "SCH{$customerCode}-{$date}-" . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }

    public function getFrequencyInDays(): int
    {
        return match ($this->frequency) {
            'weekly' => 7,
            'bi_weekly' => 14,
            'monthly' => 30,
            'quarterly' => 90,
            'custom' => $this->frequency_days ?? 30,
            default => 30
        };
    }

    public function calculateNextPaymentDate(): Carbon
    {
        $frequencyDays = $this->getFrequencyInDays();
        return $this->next_payment_date->addDays($frequencyDays);
    }

    public function processPayment(float $amount, string $paymentReference = ''): bool
    {
        if (!$this->is_active || $amount <= 0) {
            return false;
        }

        $lateFee = 0;
        if ($this->is_overdue && !$this->is_in_grace_period) {
            $lateFee = $this->calculateLateFee($amount);
        }

        $this->paid_amount += $amount;
        $this->remaining_amount = max(0, $this->total_amount - $this->paid_amount);
        $this->completed_installments += 1;
        $this->last_payment_date = now()->toDateString();
        $this->total_late_fees += $lateFee;

        // Update next payment date
        if ($this->completed_installments < $this->total_installments) {
            $this->next_payment_date = $this->calculateNextPaymentDate();
        } else {
            $this->status = 'completed';
            $this->next_payment_date = null;
        }

        $this->save();

        return true;
    }

    public function calculateLateFee(float $paymentAmount): float
    {
        if ($this->late_fee_percentage > 0) {
            return ($paymentAmount * $this->late_fee_percentage) / 100;
        }

        return $this->late_fee_amount;
    }

    public function suspend(string $reason = ''): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $this->update([
            'status' => 'suspended',
            'notes' => $reason,
        ]);

        return true;
    }

    public function resume(): bool
    {
        if (!$this->is_suspended) {
            return false;
        }

        $this->update(['status' => 'active']);
        return true;
    }

    public function cancel(string $reason = ''): bool
    {
        if ($this->is_completed || $this->is_cancelled) {
            return false;
        }

        $this->update([
            'status' => 'cancelled',
            'notes' => $reason,
        ]);

        return true;
    }

    public function markAsDefaulted(string $reason = ''): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $this->update([
            'status' => 'defaulted',
            'notes' => $reason,
        ]);

        return true;
    }

    public function modifySchedule(array $modifications, int $approvedBy): bool
    {
        $allowedFields = [
            'installment_amount',
            'frequency',
            'frequency_days',
            'total_installments',
            'end_date',
            'late_fee_percentage',
            'late_fee_amount',
            'grace_period_days',
        ];

        $updateData = array_intersect_key($modifications, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        // Recalculate remaining amount and end date if installment amount changed
        if (isset($updateData['installment_amount'])) {
            $this->remaining_amount = $this->total_amount - $this->paid_amount;
        }

        $updateData['approved_by'] = $approvedBy;
        $updateData['approved_at'] = now();

        $this->update($updateData);
        return true;
    }

    public function generateReminder(): array
    {
        if (!$this->auto_generate_reminders || !$this->is_active) {
            return [];
        }

        $reminderDate = $this->next_payment_date->subDays($this->reminder_days_before);

        if ($reminderDate->isFuture()) {
            return [];
        }

        return [
            'customer_id' => $this->customer_id,
            'schedule_id' => $this->id,
            'type' => 'payment_reminder',
            'due_date' => $this->next_payment_date->toDateString(),
            'amount' => $this->installment_amount,
            'message' => "Payment reminder: {$this->schedule_name} installment of " .
                number_format($this->installment_amount, 2) . " is due on " .
                $this->next_payment_date->format('d M Y'),
        ];
    }

    // Query Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', 'suspended');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeDefaulted(Builder $query): Builder
    {
        return $query->where('status', 'defaulted');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('next_payment_date', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereDate('next_payment_date', today());
    }

    public function scopeDueThisWeek(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereBetween('next_payment_date', [now(), now()->endOfWeek()]);
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForSale(Builder $query, int $saleId): Builder
    {
        return $query->where('sale_id', $saleId);
    }

    public function scopeByFrequency(Builder $query, string $frequency): Builder
    {
        return $query->where('frequency', $frequency);
    }

    public function scopeSearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('schedule_number', 'like', "%{$search}%")
                ->orWhere('schedule_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
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

        static::creating(function ($schedule) {
            if (empty($schedule->schedule_number)) {
                $schedule->schedule_number = $schedule->generateScheduleNumber();
            }

            if (empty($schedule->remaining_amount)) {
                $schedule->remaining_amount = $schedule->total_amount;
            }
        });

        static::created(function ($schedule) {
            // Update customer payment schedule tracking
            $schedule->customer->updateArBalance();
        });

        static::updated(function ($schedule) {
            // Update customer when schedule is modified
            $schedule->customer->updateArBalance();
        });
    }
}
