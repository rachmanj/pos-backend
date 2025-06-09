<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class CustomerAgingSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'snapshot_date',
        'snapshot_type',
        'current_amount',
        'days_31_60',
        'days_61_90',
        'days_91_120',
        'days_over_120',
        'total_outstanding',
        'overdue_amount',
        'overdue_invoices_count',
        'total_invoices_count',
        'days_oldest_invoice',
        'credit_limit',
        'available_credit',
        'credit_utilization_percentage',
        'average_days_to_pay',
        'payment_terms_days',
        'payment_reliability_score',
        'late_payments_count',
        'risk_level',
        'collection_status',
        'risk_notes',
        'generated_by',
        'generated_at',
        'calculation_metadata',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'generated_at' => 'datetime',
        'current_amount' => 'decimal:2',
        'days_31_60' => 'decimal:2',
        'days_61_90' => 'decimal:2',
        'days_91_120' => 'decimal:2',
        'days_over_120' => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'overdue_amount' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'available_credit' => 'decimal:2',
        'credit_utilization_percentage' => 'decimal:2',
        'average_days_to_pay' => 'decimal:2',
        'payment_reliability_score' => 'decimal:2',
        'overdue_invoices_count' => 'integer',
        'total_invoices_count' => 'integer',
        'days_oldest_invoice' => 'integer',
        'payment_terms_days' => 'integer',
        'late_payments_count' => 'integer',
        'calculation_metadata' => 'array',
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    // Accessors
    public function getIsCurrentAttribute(): bool
    {
        return $this->snapshot_date->isToday();
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->overdue_amount > 0;
    }

    public function getOverduePercentageAttribute(): float
    {
        if ($this->total_outstanding <= 0) {
            return 0;
        }

        return ($this->overdue_amount / $this->total_outstanding) * 100;
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

    public function getCollectionStatusColorAttribute(): string
    {
        return match ($this->collection_status) {
            'current' => 'green',
            'follow_up' => 'yellow',
            'collection' => 'orange',
            'legal' => 'red',
            'write_off' => 'gray',
            default => 'gray'
        };
    }

    public function getSnapshotTypeDisplayAttribute(): string
    {
        return match ($this->snapshot_type) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            'quarterly' => 'Quarterly',
            'manual' => 'Manual',
            default => 'Daily'
        };
    }

    public function getDaysOldAttribute(): int
    {
        return $this->snapshot_date->diffInDays(now());
    }

    public function getAgingDistributionAttribute(): array
    {
        $total = $this->total_outstanding;

        if ($total <= 0) {
            return [
                'current' => 0,
                'days_31_60' => 0,
                'days_61_90' => 0,
                'days_91_120' => 0,
                'days_over_120' => 0,
            ];
        }

        return [
            'current' => ($this->current_amount / $total) * 100,
            'days_31_60' => ($this->days_31_60 / $total) * 100,
            'days_61_90' => ($this->days_61_90 / $total) * 100,
            'days_91_120' => ($this->days_91_120 / $total) * 100,
            'days_over_120' => ($this->days_over_120 / $total) * 100,
        ];
    }

    public function getWorstAgingBucketAttribute(): string
    {
        if ($this->days_over_120 > 0) {
            return 'over_120';
        } elseif ($this->days_91_120 > 0) {
            return 'days_91_120';
        } elseif ($this->days_61_90 > 0) {
            return 'days_61_90';
        } elseif ($this->days_31_60 > 0) {
            return 'days_31_60';
        } else {
            return 'current';
        }
    }

    public function getWorstAgingBucketDisplayAttribute(): string
    {
        return match ($this->worst_aging_bucket) {
            'current' => '0-30 days',
            'days_31_60' => '31-60 days',
            'days_61_90' => '61-90 days',
            'days_91_120' => '91-120 days',
            'over_120' => 'Over 120 days',
            default => 'Current'
        };
    }

    // Business Logic Methods
    public static function generateForCustomer(Customer $customer, string $type = 'daily', int $generatedBy = null): self
    {
        $snapshot = new self();
        $snapshot->customer_id = $customer->id;
        $snapshot->snapshot_date = now()->toDateString();
        $snapshot->snapshot_type = $type;
        $snapshot->generated_by = $generatedBy;
        $snapshot->generated_at = now();

        // Calculate aging buckets
        $agingData = $snapshot->calculateAgingBuckets($customer);
        $snapshot->fill($agingData);

        // Calculate credit information
        $creditData = $snapshot->calculateCreditInfo($customer);
        $snapshot->fill($creditData);

        // Calculate payment behavior
        $paymentData = $snapshot->calculatePaymentBehavior($customer);
        $snapshot->fill($paymentData);

        // Determine risk level and collection status
        $riskData = $snapshot->calculateRiskAssessment($customer);
        $snapshot->fill($riskData);

        $snapshot->save();
        return $snapshot;
    }

    protected function calculateAgingBuckets(Customer $customer): array
    {
        $outstandingSales = $customer->sales()
            ->whereIn('payment_status', ['unpaid', 'partial', 'overdue'])
            ->where('outstanding_amount', '>', 0)
            ->get();

        $buckets = [
            'current_amount' => 0,
            'days_31_60' => 0,
            'days_61_90' => 0,
            'days_91_120' => 0,
            'days_over_120' => 0,
            'total_outstanding' => 0,
            'overdue_amount' => 0,
            'overdue_invoices_count' => 0,
            'total_invoices_count' => $outstandingSales->count(),
            'days_oldest_invoice' => 0,
        ];

        foreach ($outstandingSales as $sale) {
            $daysOverdue = $sale->days_overdue;
            $amount = $sale->outstanding_amount;

            $buckets['total_outstanding'] += $amount;

            if ($daysOverdue <= 30) {
                $buckets['current_amount'] += $amount;
            } elseif ($daysOverdue <= 60) {
                $buckets['days_31_60'] += $amount;
                $buckets['overdue_amount'] += $amount;
                $buckets['overdue_invoices_count']++;
            } elseif ($daysOverdue <= 90) {
                $buckets['days_61_90'] += $amount;
                $buckets['overdue_amount'] += $amount;
                $buckets['overdue_invoices_count']++;
            } elseif ($daysOverdue <= 120) {
                $buckets['days_91_120'] += $amount;
                $buckets['overdue_amount'] += $amount;
                $buckets['overdue_invoices_count']++;
            } else {
                $buckets['days_over_120'] += $amount;
                $buckets['overdue_amount'] += $amount;
                $buckets['overdue_invoices_count']++;
            }

            $buckets['days_oldest_invoice'] = max($buckets['days_oldest_invoice'], $daysOverdue);
        }

        return $buckets;
    }

    protected function calculateCreditInfo(Customer $customer): array
    {
        $creditLimit = $customer->creditLimit;

        if (!$creditLimit) {
            return [
                'credit_limit' => 0,
                'available_credit' => 0,
                'credit_utilization_percentage' => 0,
            ];
        }

        return [
            'credit_limit' => $creditLimit->credit_limit,
            'available_credit' => $creditLimit->available_credit,
            'credit_utilization_percentage' => $creditLimit->credit_utilization_percentage,
        ];
    }

    protected function calculatePaymentBehavior(Customer $customer): array
    {
        $paidSales = $customer->sales()
            ->where('payment_status', 'paid')
            ->get();

        if ($paidSales->isEmpty()) {
            return [
                'average_days_to_pay' => 0,
                'payment_terms_days' => 30,
                'payment_reliability_score' => 100,
                'late_payments_count' => 0,
            ];
        }

        $totalDaysToPay = $paidSales->sum(function ($sale) {
            return $sale->sale_date->diffInDays($sale->last_payment_date ?? $sale->completed_at);
        });

        $averageDaysToPay = $totalDaysToPay / $paidSales->count();
        $latePaymentsCount = $paidSales->where('days_overdue', '>', 0)->count();
        $reliabilityScore = (($paidSales->count() - $latePaymentsCount) / $paidSales->count()) * 100;

        return [
            'average_days_to_pay' => $averageDaysToPay,
            'payment_terms_days' => $customer->payment_terms_days ?? 30,
            'payment_reliability_score' => $reliabilityScore,
            'late_payments_count' => $latePaymentsCount,
        ];
    }

    protected function calculateRiskAssessment(Customer $customer): array
    {
        $riskLevel = 'low';
        $collectionStatus = 'current';

        // Determine risk level based on aging and payment behavior
        if ($this->days_over_120 > 0 || $this->payment_reliability_score < 50) {
            $riskLevel = 'critical';
            $collectionStatus = 'legal';
        } elseif ($this->days_91_120 > 0 || $this->payment_reliability_score < 70) {
            $riskLevel = 'high';
            $collectionStatus = 'collection';
        } elseif ($this->days_61_90 > 0 || $this->payment_reliability_score < 85) {
            $riskLevel = 'medium';
            $collectionStatus = 'follow_up';
        } elseif ($this->overdue_amount > 0) {
            $riskLevel = 'medium';
            $collectionStatus = 'follow_up';
        }

        // Additional risk factors
        if ($this->credit_utilization_percentage > 90) {
            $riskLevel = match ($riskLevel) {
                'low' => 'medium',
                'medium' => 'high',
                'high' => 'critical',
                default => $riskLevel
            };
        }

        return [
            'risk_level' => $riskLevel,
            'collection_status' => $collectionStatus,
        ];
    }

    public function compareWith(CustomerAgingSnapshot $previousSnapshot): array
    {
        return [
            'total_outstanding_change' => $this->total_outstanding - $previousSnapshot->total_outstanding,
            'overdue_amount_change' => $this->overdue_amount - $previousSnapshot->overdue_amount,
            'credit_utilization_change' => $this->credit_utilization_percentage - $previousSnapshot->credit_utilization_percentage,
            'payment_reliability_change' => $this->payment_reliability_score - $previousSnapshot->payment_reliability_score,
            'risk_level_changed' => $this->risk_level !== $previousSnapshot->risk_level,
            'collection_status_changed' => $this->collection_status !== $previousSnapshot->collection_status,
        ];
    }

    // Query Scopes
    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('snapshot_type', $type);
    }

    public function scopeByRiskLevel(Builder $query, string $riskLevel): Builder
    {
        return $query->where('risk_level', $riskLevel);
    }

    public function scopeByCollectionStatus(Builder $query, string $status): Builder
    {
        return $query->where('collection_status', $status);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('overdue_amount', '>', 0);
    }

    public function scopeHighRisk(Builder $query): Builder
    {
        return $query->whereIn('risk_level', ['high', 'critical']);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('snapshot_date', [$startDate, $endDate]);
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('snapshot_date', 'desc');
    }

    public function scopeDaily(Builder $query): Builder
    {
        return $query->where('snapshot_type', 'daily');
    }

    public function scopeWeekly(Builder $query): Builder
    {
        return $query->where('snapshot_type', 'weekly');
    }

    public function scopeMonthly(Builder $query): Builder
    {
        return $query->where('snapshot_type', 'monthly');
    }

    // Static Methods
    public static function generateDailySnapshots(): int
    {
        $customers = Customer::whereHas('sales', function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
        })->get();

        $count = 0;
        foreach ($customers as $customer) {
            // Check if snapshot already exists for today
            $existingSnapshot = self::forCustomer($customer->id)
                ->where('snapshot_date', today())
                ->where('snapshot_type', 'daily')
                ->first();

            if (!$existingSnapshot) {
                self::generateForCustomer($customer, 'daily');
                $count++;
            }
        }

        return $count;
    }

    public static function generateWeeklySnapshots(): int
    {
        $customers = Customer::whereHas('sales', function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
        })->get();

        $count = 0;
        foreach ($customers as $customer) {
            // Check if snapshot already exists for this week
            $existingSnapshot = self::forCustomer($customer->id)
                ->whereBetween('snapshot_date', [now()->startOfWeek(), now()->endOfWeek()])
                ->where('snapshot_type', 'weekly')
                ->first();

            if (!$existingSnapshot) {
                self::generateForCustomer($customer, 'weekly');
                $count++;
            }
        }

        return $count;
    }

    public static function generateMonthlySnapshots(): int
    {
        $customers = Customer::whereHas('sales', function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
        })->get();

        $count = 0;
        foreach ($customers as $customer) {
            // Check if snapshot already exists for this month
            $existingSnapshot = self::forCustomer($customer->id)
                ->whereBetween('snapshot_date', [now()->startOfMonth(), now()->endOfMonth()])
                ->where('snapshot_type', 'monthly')
                ->first();

            if (!$existingSnapshot) {
                self::generateForCustomer($customer, 'monthly');
                $count++;
            }
        }

        return $count;
    }
}
