<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAgingSnapshot;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ARAgingService
{
    /**
     * Indonesian business standard aging buckets
     */
    const AGING_BUCKETS = [
        'current' => 0,      // 0-30 days
        'days_30' => 30,     // 31-60 days
        'days_60' => 60,     // 61-90 days
        'days_90' => 90,     // 91-120 days
        'days_120_plus' => 120  // 120+ days
    ];

    /**
     * Generate aging report for all customers or specific customer
     */
    public function generateAgingReport(array $filters = []): array
    {
        $query = Sale::query()
            ->select([
                'customer_id',
                'id',
                'sale_number',
                'total_amount',
                'outstanding_amount',
                'due_date',
                'payment_status',
                'created_at'
            ])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->with('customer:id,name,customer_code,phone,email,customer_type');

        // Apply filters
        if (isset($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        if (isset($filters['customer_type'])) {
            $query->whereHas('customer', function ($q) use ($filters) {
                $q->where('customer_type', $filters['customer_type']);
            });
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('due_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('due_date', '<=', $filters['date_to']);
        }

        $sales = $query->get();

        // Group by customer and calculate aging
        $customerAging = [];
        $totalAging = [
            'current' => 0,
            'days_30' => 0,
            'days_60' => 0,
            'days_90' => 0,
            'days_120_plus' => 0,
            'total' => 0
        ];

        foreach ($sales as $sale) {
            $customerId = $sale->customer_id;
            $daysOverdue = $this->calculateDaysOverdue($sale->due_date);
            $bucket = $this->getAgingBucket($daysOverdue);

            if (!isset($customerAging[$customerId])) {
                $customerAging[$customerId] = [
                    'customer' => $sale->customer,
                    'current' => 0,
                    'days_30' => 0,
                    'days_60' => 0,
                    'days_90' => 0,
                    'days_120_plus' => 0,
                    'total' => 0,
                    'sales_count' => 0,
                    'oldest_due_date' => $sale->due_date,
                    'sales' => []
                ];
            }

            $customerAging[$customerId][$bucket] += $sale->outstanding_amount;
            $customerAging[$customerId]['total'] += $sale->outstanding_amount;
            $customerAging[$customerId]['sales_count']++;
            $customerAging[$customerId]['sales'][] = [
                'sale_number' => $sale->sale_number,
                'outstanding_amount' => $sale->outstanding_amount,
                'due_date' => $sale->due_date,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket
            ];

            // Update oldest due date
            if ($sale->due_date < $customerAging[$customerId]['oldest_due_date']) {
                $customerAging[$customerId]['oldest_due_date'] = $sale->due_date;
            }

            // Add to totals
            $totalAging[$bucket] += $sale->outstanding_amount;
            $totalAging['total'] += $sale->outstanding_amount;
        }

        // Sort customers by total outstanding amount (descending)
        uasort($customerAging, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return [
            'report_date' => now()->toDateString(),
            'total_customers' => count($customerAging),
            'total_aging' => $totalAging,
            'customer_aging' => array_values($customerAging),
            'aging_percentages' => $this->calculateAgingPercentages($totalAging),
            'risk_analysis' => $this->analyzeRisk($customerAging)
        ];
    }

    /**
     * Get customer aging analysis
     */
    public function getCustomerAging(int $customerId): array
    {
        $sales = Sale::where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->get();

        $aging = [
            'current' => 0,
            'days_30' => 0,
            'days_60' => 0,
            'days_90' => 0,
            'days_120_plus' => 0,
            'total' => 0
        ];

        $salesByBucket = [
            'current' => [],
            'days_30' => [],
            'days_60' => [],
            'days_90' => [],
            'days_120_plus' => []
        ];

        foreach ($sales as $sale) {
            $daysOverdue = $this->calculateDaysOverdue($sale->due_date);
            $bucket = $this->getAgingBucket($daysOverdue);

            $aging[$bucket] += $sale->outstanding_amount;
            $aging['total'] += $sale->outstanding_amount;

            $salesByBucket[$bucket][] = [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'outstanding_amount' => $sale->outstanding_amount,
                'due_date' => $sale->due_date,
                'days_overdue' => $daysOverdue
            ];
        }

        return [
            'customer_id' => $customerId,
            'aging' => $aging,
            'sales_by_bucket' => $salesByBucket,
            'aging_percentages' => $this->calculateAgingPercentages($aging),
            'risk_score' => $this->calculateCustomerRiskScore($aging)
        ];
    }

    /**
     * Update customer aging snapshot
     */
    public function updateCustomerAging(int $customerId): CustomerAgingSnapshot
    {
        $agingData = $this->getCustomerAging($customerId);

        return CustomerAgingSnapshot::updateOrCreate(
            [
                'customer_id' => $customerId,
                'snapshot_date' => now()->toDateString()
            ],
            [
                'current_amount' => $agingData['aging']['current'],
                'days_30' => $agingData['aging']['days_30'],
                'days_60' => $agingData['aging']['days_60'],
                'days_90' => $agingData['aging']['days_90'],
                'days_120_plus' => $agingData['aging']['days_120_plus'],
                'total_outstanding' => $agingData['aging']['total'],
                'risk_score' => $agingData['risk_score'],
                'sales_count' => array_sum(array_map('count', $agingData['sales_by_bucket']))
            ]
        );
    }

    /**
     * Generate aging snapshots for all customers
     */
    public function generateDailySnapshots(): array
    {
        $customers = Customer::whereHas('sales', function ($query) {
            $query->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('outstanding_amount', '>', 0);
        })->get();

        $results = [];

        foreach ($customers as $customer) {
            try {
                $snapshot = $this->updateCustomerAging($customer->id);
                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'status' => 'success',
                    'snapshot_id' => $snapshot->id
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'snapshot_date' => now()->toDateString(),
            'total_customers' => count($customers),
            'successful_snapshots' => count(array_filter($results, fn($r) => $r['status'] === 'success')),
            'failed_snapshots' => count(array_filter($results, fn($r) => $r['status'] === 'error')),
            'results' => $results
        ];
    }

    /**
     * Get aging summary for dashboard
     */
    public function getAgingSummary(): array
    {
        $totalOutstanding = Sale::whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->sum('outstanding_amount');

        if ($totalOutstanding == 0) {
            return [
                'total_outstanding' => 0,
                'current' => 0,
                'days_30' => 0,
                'days_60' => 0,
                'days_90' => 0,
                'days_120_plus' => 0,
                'overdue_percentage' => 0,
                'high_risk_customers' => 0
            ];
        }

        $sales = Sale::whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->get();

        $aging = [
            'current' => 0,
            'days_30' => 0,
            'days_60' => 0,
            'days_90' => 0,
            'days_120_plus' => 0
        ];

        foreach ($sales as $sale) {
            $daysOverdue = $this->calculateDaysOverdue($sale->due_date);
            $bucket = $this->getAgingBucket($daysOverdue);
            $aging[$bucket] += $sale->outstanding_amount;
        }

        $overdueAmount = $aging['days_30'] + $aging['days_60'] + $aging['days_90'] + $aging['days_120_plus'];
        $overduePercentage = ($overdueAmount / $totalOutstanding) * 100;

        // Count high-risk customers (those with 60+ days overdue)
        $highRiskCustomers = Sale::whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', now()->subDays(60))
            ->distinct('customer_id')
            ->count('customer_id');

        return [
            'total_outstanding' => $totalOutstanding,
            'current' => $aging['current'],
            'days_30' => $aging['days_30'],
            'days_60' => $aging['days_60'],
            'days_90' => $aging['days_90'],
            'days_120_plus' => $aging['days_120_plus'],
            'overdue_percentage' => round($overduePercentage, 2),
            'high_risk_customers' => $highRiskCustomers
        ];
    }

    /**
     * Get aging trends over time
     */
    public function getAgingTrends(int $months = 6): array
    {
        $trends = [];
        $startDate = now()->subMonths($months)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $date = $startDate->copy()->addMonths($i);
            $monthEnd = $date->copy()->endOfMonth();

            $snapshots = CustomerAgingSnapshot::whereDate('snapshot_date', $monthEnd->toDateString())
                ->get();

            if ($snapshots->isEmpty()) {
                // If no snapshots for month end, get the latest snapshot for that month
                $snapshots = CustomerAgingSnapshot::whereYear('snapshot_date', $date->year)
                    ->whereMonth('snapshot_date', $date->month)
                    ->latest('snapshot_date')
                    ->get();
            }

            $monthData = [
                'month' => $date->format('Y-m'),
                'month_name' => $date->format('M Y'),
                'current' => $snapshots->sum('current_amount'),
                'days_30' => $snapshots->sum('days_30'),
                'days_60' => $snapshots->sum('days_60'),
                'days_90' => $snapshots->sum('days_90'),
                'days_120_plus' => $snapshots->sum('days_120_plus'),
                'total_outstanding' => $snapshots->sum('total_outstanding'),
                'customers_count' => $snapshots->count()
            ];

            $monthData['overdue_amount'] = $monthData['days_30'] + $monthData['days_60'] +
                $monthData['days_90'] + $monthData['days_120_plus'];

            $monthData['overdue_percentage'] = $monthData['total_outstanding'] > 0
                ? round(($monthData['overdue_amount'] / $monthData['total_outstanding']) * 100, 2)
                : 0;

            $trends[] = $monthData;
        }

        return $trends;
    }

    /**
     * Calculate days overdue from due date
     */
    private function calculateDaysOverdue(?string $dueDate): int
    {
        if (!$dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate);
        $now = now();

        return $now->gt($due) ? $now->diffInDays($due) : 0;
    }

    /**
     * Get aging bucket for days overdue
     */
    private function getAgingBucket(int $daysOverdue): string
    {
        if ($daysOverdue <= 30) {
            return 'current';
        } elseif ($daysOverdue <= 60) {
            return 'days_30';
        } elseif ($daysOverdue <= 90) {
            return 'days_60';
        } elseif ($daysOverdue <= 120) {
            return 'days_90';
        } else {
            return 'days_120_plus';
        }
    }

    /**
     * Calculate aging percentages
     */
    private function calculateAgingPercentages(array $aging): array
    {
        $total = $aging['total'];

        if ($total == 0) {
            return [
                'current' => 0,
                'days_30' => 0,
                'days_60' => 0,
                'days_90' => 0,
                'days_120_plus' => 0
            ];
        }

        return [
            'current' => round(($aging['current'] / $total) * 100, 2),
            'days_30' => round(($aging['days_30'] / $total) * 100, 2),
            'days_60' => round(($aging['days_60'] / $total) * 100, 2),
            'days_90' => round(($aging['days_90'] / $total) * 100, 2),
            'days_120_plus' => round(($aging['days_120_plus'] / $total) * 100, 2)
        ];
    }

    /**
     * Calculate customer risk score based on aging
     */
    private function calculateCustomerRiskScore(array $aging): int
    {
        $total = $aging['total'];

        if ($total == 0) {
            return 0;
        }

        $score = 0;

        // Weight aging buckets by risk
        $score += ($aging['current'] / $total) * 10;      // Low risk
        $score += ($aging['days_30'] / $total) * 30;      // Medium risk
        $score += ($aging['days_60'] / $total) * 60;      // High risk
        $score += ($aging['days_90'] / $total) * 80;      // Very high risk
        $score += ($aging['days_120_plus'] / $total) * 100; // Extreme risk

        return min(100, round($score));
    }

    /**
     * Analyze risk across all customers
     */
    private function analyzeRisk(array $customerAging): array
    {
        $riskCategories = [
            'low' => 0,      // 0-30 risk score
            'medium' => 0,   // 31-60 risk score
            'high' => 0,     // 61-80 risk score
            'critical' => 0  // 81-100 risk score
        ];

        foreach ($customerAging as $customer) {
            $riskScore = $this->calculateCustomerRiskScore($customer);

            if ($riskScore <= 30) {
                $riskCategories['low']++;
            } elseif ($riskScore <= 60) {
                $riskCategories['medium']++;
            } elseif ($riskScore <= 80) {
                $riskCategories['high']++;
            } else {
                $riskCategories['critical']++;
            }
        }

        return $riskCategories;
    }

    /**
     * Get overdue customers list
     */
    public function getOverdueCustomers(int $daysPastDue = 1): array
    {
        $cutoffDate = now()->subDays($daysPastDue);

        $overdueCustomers = Sale::select([
            'customer_id',
            DB::raw('COUNT(*) as overdue_sales_count'),
            DB::raw('SUM(outstanding_amount) as total_overdue_amount'),
            DB::raw('MIN(due_date) as oldest_due_date'),
            DB::raw('MAX(DATEDIFF(NOW(), due_date)) as max_days_overdue')
        ])
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', $cutoffDate)
            ->groupBy('customer_id')
            ->with('customer:id,name,customer_code,phone,email,customer_type')
            ->orderByDesc('total_overdue_amount')
            ->get();

        return $overdueCustomers->map(function ($item) {
            return [
                'customer' => $item->customer,
                'overdue_sales_count' => $item->overdue_sales_count,
                'total_overdue_amount' => $item->total_overdue_amount,
                'oldest_due_date' => $item->oldest_due_date,
                'max_days_overdue' => $item->max_days_overdue,
                'risk_level' => $this->getRiskLevel($item->max_days_overdue)
            ];
        })->toArray();
    }

    /**
     * Get risk level based on days overdue
     */
    private function getRiskLevel(int $daysOverdue): string
    {
        if ($daysOverdue <= 30) {
            return 'low';
        } elseif ($daysOverdue <= 60) {
            return 'medium';
        } elseif ($daysOverdue <= 90) {
            return 'high';
        } else {
            return 'critical';
        }
    }
}
