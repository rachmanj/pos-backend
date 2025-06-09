<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class CustomerCreditLimit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'credit_limit',
        'current_balance',
        'available_credit',
        'overdue_amount',
        'payment_terms_days',
        'payment_terms_type',
        'early_payment_discount_percentage',
        'early_payment_discount_days',
        'credit_status',
        'credit_score',
        'payment_reliability_score',
        'last_review_date',
        'next_review_date',
        'days_past_due',
        'payment_delay_count',
        'late_payment_count',
        'approved_by',
        'reviewed_by',
        'approved_at',
        'last_reviewed_at',
        'requires_approval',
        'auto_approval_limit',
        'credit_notes',
        'risk_assessment',
    ];

    protected $casts = [
        'last_review_date' => 'date',
        'next_review_date' => 'date',
        'approved_at' => 'datetime',
        'last_reviewed_at' => 'datetime',
        'credit_limit' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'available_credit' => 'decimal:2',
        'overdue_amount' => 'decimal:2',
        'early_payment_discount_percentage' => 'decimal:2',
        'payment_reliability_score' => 'decimal:2',
        'auto_approval_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'credit_score' => 'integer',
        'days_past_due' => 'integer',
        'payment_delay_count' => 'integer',
        'late_payment_count' => 'integer',
        'early_payment_discount_days' => 'integer',
        'requires_approval' => 'boolean',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Accessors
    public function getCreditUtilizationPercentageAttribute(): float
    {
        if ($this->credit_limit <= 0) {
            return 0;
        }

        return ($this->current_balance / $this->credit_limit) * 100;
    }

    public function getIsOverLimitAttribute(): bool
    {
        return $this->current_balance > $this->credit_limit;
    }

    public function getIsNearLimitAttribute(): bool
    {
        return $this->credit_utilization_percentage >= 80;
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->overdue_amount > 0;
    }

    public function getIsCreditBlockedAttribute(): bool
    {
        return in_array($this->credit_status, ['blocked', 'suspended', 'defaulted']);
    }

    public function getCanExtendCreditAttribute(): bool
    {
        return $this->credit_status === 'good' && !$this->is_over_limit && $this->days_past_due <= 30;
    }

    public function getCreditStatusColorAttribute(): string
    {
        return match ($this->credit_status) {
            'good' => 'green',
            'warning' => 'yellow',
            'blocked' => 'red',
            'suspended' => 'orange',
            'defaulted' => 'red',
            default => 'gray'
        };
    }

    public function getPaymentTermsDisplayAttribute(): string
    {
        return match ($this->payment_terms_type) {
            'cash' => 'Cash Only',
            'net_15' => 'Net 15 Days',
            'net_30' => 'Net 30 Days',
            'net_60' => 'Net 60 Days',
            'net_90' => 'Net 90 Days',
            'custom' => "Net {$this->payment_terms_days} Days",
            default => 'Net 30 Days'
        };
    }

    public function getRiskLevelAttribute(): string
    {
        if ($this->credit_score >= 80 && $this->payment_reliability_score >= 90) {
            return 'low';
        } elseif ($this->credit_score >= 60 && $this->payment_reliability_score >= 70) {
            return 'medium';
        } elseif ($this->credit_score >= 40 && $this->payment_reliability_score >= 50) {
            return 'high';
        } else {
            return 'critical';
        }
    }

    public function getRiskLevelColorAttribute(): string
    {
        return match ($this->risk_level) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function getDaysUntilReviewAttribute(): ?int
    {
        return $this->next_review_date ? now()->diffInDays($this->next_review_date, false) : null;
    }

    public function getIsReviewOverdueAttribute(): bool
    {
        return $this->next_review_date && $this->next_review_date->isPast();
    }

    // Business Logic Methods
    public function updateBalance(): void
    {
        $outstandingAmount = $this->customer->sales()
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
            ->sum('outstanding_amount');

        $overdueAmount = $this->customer->sales()
            ->where('payment_status', 'overdue')
            ->sum('outstanding_amount');

        $availableCredit = max(0, $this->credit_limit - $outstandingAmount);

        $this->update([
            'current_balance' => $outstandingAmount,
            'overdue_amount' => $overdueAmount,
            'available_credit' => $availableCredit,
        ]);
    }

    public function canApproveCredit(float $amount): bool
    {
        if ($this->is_credit_blocked) {
            return false;
        }

        $newBalance = $this->current_balance + $amount;

        if ($this->requires_approval && $amount > $this->auto_approval_limit) {
            return false;
        }

        return $newBalance <= $this->credit_limit;
    }

    public function updateCreditStatus(): void
    {
        $newStatus = 'good';

        if ($this->is_over_limit) {
            $newStatus = 'blocked';
        } elseif ($this->days_past_due > 90) {
            $newStatus = 'defaulted';
        } elseif ($this->days_past_due > 60) {
            $newStatus = 'suspended';
        } elseif ($this->days_past_due > 30 || $this->is_near_limit) {
            $newStatus = 'warning';
        }

        if ($newStatus !== $this->credit_status) {
            $this->update(['credit_status' => $newStatus]);
        }
    }

    public function updateCreditScore(): void
    {
        $score = 100;

        // Deduct points for late payments
        $score -= min(30, $this->late_payment_count * 2);

        // Deduct points for payment delays
        $score -= min(20, $this->payment_delay_count);

        // Deduct points for days past due
        if ($this->days_past_due > 0) {
            $score -= min(25, $this->days_past_due / 2);
        }

        // Deduct points for high credit utilization
        if ($this->credit_utilization_percentage > 80) {
            $score -= 15;
        } elseif ($this->credit_utilization_percentage > 60) {
            $score -= 10;
        }

        // Ensure score is between 0 and 100
        $score = max(0, min(100, $score));

        $this->update(['credit_score' => $score]);
    }

    public function updatePaymentReliabilityScore(): void
    {
        $totalSales = $this->customer->sales()->count();

        if ($totalSales === 0) {
            return;
        }

        $onTimePaidSales = $this->customer->sales()
            ->where('payment_status', 'paid')
            ->where('days_overdue', 0)
            ->count();

        $reliabilityScore = ($onTimePaidSales / $totalSales) * 100;

        $this->update(['payment_reliability_score' => $reliabilityScore]);
    }

    public function scheduleReview(int $days = 90): void
    {
        $this->update([
            'next_review_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function conductReview(int $reviewedBy, string $notes = ''): void
    {
        $this->updateBalance();
        $this->updateCreditScore();
        $this->updatePaymentReliabilityScore();
        $this->updateCreditStatus();

        $this->update([
            'reviewed_by' => $reviewedBy,
            'last_reviewed_at' => now(),
            'last_review_date' => now()->toDateString(),
            'risk_assessment' => $notes,
        ]);

        $this->scheduleReview();
    }

    public function increaseCreditLimit(float $newLimit, int $approvedBy, string $reason = ''): bool
    {
        if ($newLimit <= $this->credit_limit) {
            return false;
        }

        $this->update([
            'credit_limit' => $newLimit,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'credit_notes' => $reason,
        ]);

        $this->updateBalance();
        return true;
    }

    public function decreaseCreditLimit(float $newLimit, int $approvedBy, string $reason = ''): bool
    {
        if ($newLimit >= $this->credit_limit) {
            return false;
        }

        $this->update([
            'credit_limit' => $newLimit,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'credit_notes' => $reason,
        ]);

        $this->updateBalance();
        $this->updateCreditStatus();
        return true;
    }

    public function blockCredit(string $reason = ''): void
    {
        $this->update([
            'credit_status' => 'blocked',
            'credit_notes' => $reason,
        ]);
    }

    public function unblockCredit(int $approvedBy, string $reason = ''): void
    {
        $this->update([
            'credit_status' => 'good',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'credit_notes' => $reason,
        ]);
    }

    // Query Scopes
    public function scopeGoodCredit(Builder $query): Builder
    {
        return $query->where('credit_status', 'good');
    }

    public function scopeWarningCredit(Builder $query): Builder
    {
        return $query->where('credit_status', 'warning');
    }

    public function scopeBlockedCredit(Builder $query): Builder
    {
        return $query->whereIn('credit_status', ['blocked', 'suspended', 'defaulted']);
    }

    public function scopeOverLimit(Builder $query): Builder
    {
        return $query->whereRaw('current_balance > credit_limit');
    }

    public function scopeNearLimit(Builder $query): Builder
    {
        return $query->whereRaw('(current_balance / credit_limit) * 100 >= 80');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('overdue_amount', '>', 0);
    }

    public function scopeReviewOverdue(Builder $query): Builder
    {
        return $query->where('next_review_date', '<', now());
    }

    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->where('credit_score', '<', 60)
            ->orWhere('payment_reliability_score', '<', 70);
    }

    public function scopeByRiskLevel(Builder $query, string $riskLevel): Builder
    {
        return match ($riskLevel) {
            'low' => $query->where('credit_score', '>=', 80)->where('payment_reliability_score', '>=', 90),
            'medium' => $query->where('credit_score', '>=', 60)->where('credit_score', '<', 80),
            'high' => $query->where('credit_score', '>=', 40)->where('credit_score', '<', 60),
            'critical' => $query->where('credit_score', '<', 40)->orWhere('payment_reliability_score', '<', 50),
            default => $query
        };
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($creditLimit) {
            if (empty($creditLimit->next_review_date)) {
                $creditLimit->next_review_date = now()->addDays(90)->toDateString();
            }
        });

        static::created(function ($creditLimit) {
            $creditLimit->updateBalance();
        });

        static::updated(function ($creditLimit) {
            // Update customer credit status when credit limit changes
            $creditLimit->customer->updateArBalance();
        });
    }
}
