<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerCreditLimit;
use App\Models\Sale;
use App\Models\CustomerPaymentReceive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CreditManagementService
{
    /**
     * Get or create customer credit limit
     */
    public function getCustomerCreditLimit(int $customerId): CustomerCreditLimit
    {
        $creditLimit = CustomerCreditLimit::where('customer_id', $customerId)->first();

        if (!$creditLimit) {
            $creditLimit = $this->createDefaultCreditLimit($customerId);
        }

        return $creditLimit;
    }

    /**
     * Create default credit limit for customer
     */
    public function createDefaultCreditLimit(int $customerId): CustomerCreditLimit
    {
        $customer = Customer::findOrFail($customerId);

        return CustomerCreditLimit::create([
            'customer_id' => $customerId,
            'credit_limit' => $this->calculateInitialCreditLimit($customer),
            'current_balance' => 0,
            'available_credit' => 0,
            'overdue_amount' => 0,
            'payment_terms_days' => 30,
            'payment_terms_type' => 'net_30',
            'credit_status' => 'good',
            'credit_score' => 100,
            'payment_reliability_score' => 100,
            'last_review_date' => now()->toDateString(),
            'next_review_date' => now()->addMonths(6)->toDateString(),
            'approved_by' => Auth::id(),
            'approved_at' => now()
        ]);
    }

    /**
     * Update customer credit limit
     */
    public function updateCreditLimit(
        int $customerId,
        float $newLimit,
        string $reason,
        ?int $paymentTermsDays = null,
        ?string $paymentTermsType = null
    ): CustomerCreditLimit {
        $creditLimit = $this->getCustomerCreditLimit($customerId);

        $updateData = [
            'credit_limit' => $newLimit,
            'available_credit' => $newLimit - $creditLimit->current_balance,
            'reviewed_by' => Auth::id(),
            'last_reviewed_at' => now(),
            'next_review_date' => now()->addMonths(6)->toDateString(),
            'credit_notes' => $reason
        ];

        if ($paymentTermsDays) {
            $updateData['payment_terms_days'] = $paymentTermsDays;
        }

        if ($paymentTermsType) {
            $updateData['payment_terms_type'] = $paymentTermsType;
        }

        $creditLimit->update($updateData);

        // Update credit status based on new limit
        $this->updateCreditStatus($creditLimit);

        return $creditLimit->fresh();
    }

    /**
     * Update customer AR balance
     */
    public function updateCustomerBalance(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);
        $creditLimit = $this->getCustomerCreditLimit($customerId);

        // Calculate current AR balance from outstanding sales
        $currentBalance = Sale::where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->sum('outstanding_amount');

        // Calculate overdue amount
        $overdueAmount = Sale::where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->sum('outstanding_amount');

        // Update customer
        $customer->update([
            'current_ar_balance' => $currentBalance,
            'last_payment_date' => CustomerPaymentReceive::where('customer_id', $customerId)
                ->where('status', 'completed')
                ->latest('payment_date')
                ->value('payment_date')
        ]);

        // Update credit limit
        $creditLimit->update([
            'current_balance' => $currentBalance,
            'available_credit' => max(0, $creditLimit->credit_limit - $currentBalance),
            'overdue_amount' => $overdueAmount,
            'days_past_due' => $this->calculateDaysPastDue($customerId)
        ]);

        // Update credit status and scores
        $this->updateCreditStatus($creditLimit);
        $this->updatePaymentReliabilityScore($creditLimit);
    }

    /**
     * Check if customer can make a credit sale
     */
    public function canMakeCreditSale(int $customerId, float $saleAmount): array
    {
        $creditLimit = $this->getCustomerCreditLimit($customerId);
        $result = [
            'can_proceed' => false,
            'reason' => '',
            'available_credit' => $creditLimit->available_credit,
            'credit_status' => $creditLimit->credit_status,
            'requires_approval' => false
        ];

        // Check credit status
        if (in_array($creditLimit->credit_status, ['blocked', 'suspended', 'defaulted'])) {
            $result['reason'] = 'Customer credit is ' . $creditLimit->credit_status;
            return $result;
        }

        // Check available credit
        if ($saleAmount > $creditLimit->available_credit) {
            $result['reason'] = 'Sale amount exceeds available credit limit';
            return $result;
        }

        // Check if approval is required
        if ($saleAmount > $creditLimit->auto_approval_limit) {
            $result['requires_approval'] = true;
        }

        // Check payment reliability
        if ($creditLimit->payment_reliability_score < 70) {
            $result['requires_approval'] = true;
        }

        $result['can_proceed'] = true;
        return $result;
    }

    /**
     * Update credit status based on current situation
     */
    public function updateCreditStatus(CustomerCreditLimit $creditLimit): void
    {
        $utilizationRatio = $creditLimit->credit_limit > 0
            ? ($creditLimit->current_balance / $creditLimit->credit_limit) * 100
            : 0;

        $daysPastDue = $creditLimit->days_past_due;
        $paymentScore = $creditLimit->payment_reliability_score;

        $newStatus = 'good';

        // Determine status based on multiple factors
        if ($daysPastDue > 120 || $paymentScore < 30) {
            $newStatus = 'defaulted';
        } elseif ($daysPastDue > 90 || $paymentScore < 50) {
            $newStatus = 'suspended';
        } elseif ($daysPastDue > 60 || $utilizationRatio > 95 || $paymentScore < 70) {
            $newStatus = 'blocked';
        } elseif ($daysPastDue > 30 || $utilizationRatio > 80 || $paymentScore < 85) {
            $newStatus = 'warning';
        }

        $creditLimit->update(['credit_status' => $newStatus]);
    }

    /**
     * Update payment reliability score
     */
    public function updatePaymentReliabilityScore(CustomerCreditLimit $creditLimit): void
    {
        $customerId = $creditLimit->customer_id;

        // Get payment history for the last 12 months
        $payments = CustomerPaymentReceive::where('customer_id', $customerId)
            ->where('payment_date', '>=', now()->subMonths(12))
            ->where('status', 'completed')
            ->get();

        $sales = Sale::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->get();

        if ($sales->isEmpty()) {
            return; // No sales history to evaluate
        }

        $score = 100; // Start with perfect score

        // Factor 1: Payment timeliness (40% weight)
        $timelinessScore = $this->calculateTimelinessScore($sales);
        $score = ($score * 0.6) + ($timelinessScore * 0.4);

        // Factor 2: Payment consistency (30% weight)
        $consistencyScore = $this->calculateConsistencyScore($payments);
        $score = ($score * 0.7) + ($consistencyScore * 0.3);

        // Factor 3: Current overdue status (30% weight)
        $overdueScore = $this->calculateOverdueScore($creditLimit);
        $score = ($score * 0.7) + ($overdueScore * 0.3);

        // Update scores and counts
        $creditLimit->update([
            'payment_reliability_score' => round($score, 2),
            'credit_score' => $this->calculateCreditScore($creditLimit, $score),
            'payment_delay_count' => $this->countPaymentDelays($customerId),
            'late_payment_count' => $this->countLatePayments($customerId)
        ]);
    }

    /**
     * Calculate timeliness score based on payment history
     */
    private function calculateTimelinessScore(object $sales): float
    {
        $totalSales = $sales->count();
        if ($totalSales === 0) return 100;

        $onTimePayments = $sales->filter(function ($sale) {
            return $sale->payment_status === 'paid' &&
                $sale->last_payment_date &&
                $sale->last_payment_date <= $sale->due_date;
        })->count();

        return ($onTimePayments / $totalSales) * 100;
    }

    /**
     * Calculate consistency score based on payment patterns
     */
    private function calculateConsistencyScore(object $payments): float
    {
        if ($payments->count() < 2) return 100;

        $intervals = [];
        $sortedPayments = $payments->sortBy('payment_date');

        for ($i = 1; $i < $sortedPayments->count(); $i++) {
            $current = Carbon::parse($sortedPayments[$i]->payment_date);
            $previous = Carbon::parse($sortedPayments[$i - 1]->payment_date);
            $intervals[] = $current->diffInDays($previous);
        }

        if (empty($intervals)) return 100;

        $avgInterval = array_sum($intervals) / count($intervals);
        $variance = 0;

        foreach ($intervals as $interval) {
            $variance += pow($interval - $avgInterval, 2);
        }

        $variance = $variance / count($intervals);
        $standardDeviation = sqrt($variance);

        // Lower standard deviation = higher consistency score
        $consistencyScore = max(0, 100 - ($standardDeviation * 2));

        return $consistencyScore;
    }

    /**
     * Calculate overdue score
     */
    private function calculateOverdueScore(CustomerCreditLimit $creditLimit): float
    {
        $daysPastDue = $creditLimit->days_past_due;

        if ($daysPastDue <= 0) return 100;
        if ($daysPastDue <= 30) return 80;
        if ($daysPastDue <= 60) return 60;
        if ($daysPastDue <= 90) return 40;
        if ($daysPastDue <= 120) return 20;

        return 0;
    }

    /**
     * Calculate overall credit score
     */
    private function calculateCreditScore(CustomerCreditLimit $creditLimit, float $reliabilityScore): int
    {
        $score = $reliabilityScore;

        // Adjust based on credit utilization
        $utilizationRatio = $creditLimit->credit_limit > 0
            ? ($creditLimit->current_balance / $creditLimit->credit_limit) * 100
            : 0;

        if ($utilizationRatio > 90) {
            $score -= 20;
        } elseif ($utilizationRatio > 75) {
            $score -= 10;
        } elseif ($utilizationRatio > 50) {
            $score -= 5;
        }

        // Adjust based on overdue amount
        if ($creditLimit->overdue_amount > 0) {
            $overdueRatio = $creditLimit->current_balance > 0
                ? ($creditLimit->overdue_amount / $creditLimit->current_balance) * 100
                : 0;

            $score -= ($overdueRatio * 0.5);
        }

        return max(0, min(100, round($score)));
    }

    /**
     * Calculate days past due for customer
     */
    private function calculateDaysPastDue(int $customerId): int
    {
        $oldestOverdue = Sale::where('customer_id', $customerId)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->orderBy('due_date', 'asc')
            ->first();

        if (!$oldestOverdue) {
            return 0;
        }

        return now()->diffInDays($oldestOverdue->due_date);
    }

    /**
     * Count payment delays in the last 12 months
     */
    private function countPaymentDelays(int $customerId): int
    {
        return Sale::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->where('payment_status', 'paid')
            ->whereColumn('last_payment_date', '>', 'due_date')
            ->count();
    }

    /**
     * Count late payments in the last 12 months
     */
    private function countLatePayments(int $customerId): int
    {
        return Sale::where('customer_id', $customerId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('due_date', '<', now())
            ->count();
    }

    /**
     * Calculate initial credit limit for new customer
     */
    private function calculateInitialCreditLimit(Customer $customer): float
    {
        // Base limit
        $baseLimit = 5000000; // 5M IDR

        // Adjust based on customer type
        switch ($customer->customer_type) {
            case 'vip':
                $baseLimit *= 3;
                break;
            case 'wholesale':
                $baseLimit *= 2;
                break;
            case 'member':
                $baseLimit *= 1.5;
                break;
            default:
                // Regular customer keeps base limit
                break;
        }

        return $baseLimit;
    }

    /**
     * Get credit limit summary for customer
     */
    public function getCreditSummary(int $customerId): array
    {
        $creditLimit = $this->getCustomerCreditLimit($customerId);
        $customer = Customer::findOrFail($customerId);

        return [
            'customer_id' => $customerId,
            'customer_name' => $customer->name,
            'credit_limit' => $creditLimit->credit_limit,
            'current_balance' => $creditLimit->current_balance,
            'available_credit' => $creditLimit->available_credit,
            'overdue_amount' => $creditLimit->overdue_amount,
            'credit_status' => $creditLimit->credit_status,
            'credit_score' => $creditLimit->credit_score,
            'payment_reliability_score' => $creditLimit->payment_reliability_score,
            'payment_terms' => [
                'days' => $creditLimit->payment_terms_days,
                'type' => $creditLimit->payment_terms_type,
                'early_discount_percentage' => $creditLimit->early_payment_discount_percentage,
                'early_discount_days' => $creditLimit->early_payment_discount_days
            ],
            'utilization_ratio' => $creditLimit->credit_limit > 0
                ? round(($creditLimit->current_balance / $creditLimit->credit_limit) * 100, 2)
                : 0,
            'days_past_due' => $creditLimit->days_past_due,
            'last_review_date' => $creditLimit->last_review_date,
            'next_review_date' => $creditLimit->next_review_date
        ];
    }

    /**
     * Get customers requiring credit review
     */
    public function getCustomersRequiringReview(): object
    {
        return CustomerCreditLimit::with('customer:id,name,customer_code,phone,email')
            ->where(function ($query) {
                $query->where('next_review_date', '<=', now())
                    ->orWhere('credit_status', 'warning')
                    ->orWhere('credit_status', 'blocked')
                    ->orWhere('days_past_due', '>', 30)
                    ->orWhere('payment_reliability_score', '<', 70);
            })
            ->orderBy('next_review_date', 'asc')
            ->get();
    }

    /**
     * Perform automated credit review
     */
    public function performAutomatedReview(int $customerId): array
    {
        $creditLimit = $this->getCustomerCreditLimit($customerId);

        // Update balances and scores
        $this->updateCustomerBalance($customerId);

        $creditLimit->refresh();

        $recommendations = [];

        // Analyze credit performance
        if ($creditLimit->payment_reliability_score >= 90 && $creditLimit->days_past_due === 0) {
            $recommendations[] = [
                'type' => 'increase_limit',
                'current_limit' => $creditLimit->credit_limit,
                'suggested_limit' => $creditLimit->credit_limit * 1.2,
                'reason' => 'Excellent payment history and reliability'
            ];
        } elseif ($creditLimit->payment_reliability_score < 70 || $creditLimit->days_past_due > 60) {
            $recommendations[] = [
                'type' => 'decrease_limit',
                'current_limit' => $creditLimit->credit_limit,
                'suggested_limit' => $creditLimit->credit_limit * 0.7,
                'reason' => 'Poor payment history or significant overdue amounts'
            ];
        }

        // Update review dates
        $creditLimit->update([
            'last_review_date' => now()->toDateString(),
            'next_review_date' => now()->addMonths(6)->toDateString()
        ]);

        return [
            'customer_id' => $customerId,
            'review_date' => now()->toDateString(),
            'credit_summary' => $this->getCreditSummary($customerId),
            'recommendations' => $recommendations
        ];
    }
}
