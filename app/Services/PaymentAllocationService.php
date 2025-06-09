<?php

namespace App\Services;

use App\Models\CustomerPaymentReceive;
use App\Models\CustomerPaymentAllocation;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentAllocationService
{
    /**
     * Allocate payment to a specific sale
     */
    public function allocatePayment(
        CustomerPaymentReceive $payment,
        int $saleId,
        float $amount,
        string $allocationType = 'manual',
        ?string $notes = null
    ): CustomerPaymentAllocation {
        // Validate sale belongs to same customer
        $sale = Sale::findOrFail($saleId);
        if ($sale->customer_id !== $payment->customer_id) {
            throw new \Exception('Sale does not belong to the same customer');
        }

        // Check if payment has enough unallocated amount
        if ($amount > $payment->unallocated_amount) {
            throw new \Exception('Allocation amount exceeds unallocated payment amount');
        }

        // Check if sale needs payment
        if ($sale->payment_status === 'paid') {
            throw new \Exception('Sale is already fully paid');
        }

        // Calculate maximum allocatable amount for this sale
        $maxAllocatable = $sale->outstanding_amount;
        if ($amount > $maxAllocatable) {
            $amount = $maxAllocatable;
        }

        // Create allocation
        $allocation = CustomerPaymentAllocation::create([
            'customer_payment_receive_id' => $payment->id,
            'sale_id' => $saleId,
            'customer_id' => $payment->customer_id,
            'allocated_amount' => $amount,
            'allocation_date' => now()->toDateString(),
            'allocation_type' => $allocationType,
            'status' => 'pending',
            'allocated_by' => Auth::id(),
            'notes' => $notes
        ]);

        // Update payment amounts
        $payment->increment('allocated_amount', $amount);
        $payment->decrement('unallocated_amount', $amount);

        // Update sale payment status and amounts
        $this->updateSalePaymentStatus($sale, $amount);

        // Apply allocation if payment is verified
        if (in_array($payment->workflow_status, ['verified', 'approved'])) {
            $this->applyAllocation($allocation);
        }

        return $allocation;
    }

    /**
     * Auto-allocate payment to oldest outstanding sales
     */
    public function autoAllocatePayment(CustomerPaymentReceive $payment): array
    {
        $allocations = [];
        $remainingAmount = $payment->unallocated_amount;

        if ($remainingAmount <= 0) {
            return $allocations;
        }

        // Get outstanding sales ordered by due date (oldest first)
        $outstandingSales = Sale::where('customer_id', $payment->customer_id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->orderBy('due_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        foreach ($outstandingSales as $sale) {
            if ($remainingAmount <= 0) {
                break;
            }

            $allocationAmount = min($remainingAmount, $sale->outstanding_amount);

            $allocation = $this->allocatePayment(
                $payment,
                $sale->id,
                $allocationAmount,
                'automatic',
                'Auto-allocated to oldest outstanding sale'
            );

            $allocations[] = $allocation;
            $remainingAmount -= $allocationAmount;
        }

        return $allocations;
    }

    /**
     * Apply allocation (mark as applied and update balances)
     */
    public function applyAllocation(CustomerPaymentAllocation $allocation): void
    {
        if ($allocation->status !== 'pending') {
            throw new \Exception('Allocation is not in pending status');
        }

        DB::transaction(function () use ($allocation) {
            // Mark allocation as applied
            $allocation->update([
                'status' => 'applied',
                'applied_at' => now(),
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            // Update sale payment amounts
            $sale = $allocation->sale;
            $sale->increment('paid_amount', $allocation->allocated_amount);
            $sale->decrement('outstanding_amount', $allocation->allocated_amount);

            // Update sale payment status
            $this->updateSalePaymentStatus($sale);

            // Update customer AR balance
            $customer = $allocation->customer;
            $customer->decrement('current_ar_balance', $allocation->allocated_amount);
        });
    }

    /**
     * Reverse allocation
     */
    public function reverseAllocation(CustomerPaymentAllocation $allocation, string $reason): void
    {
        if ($allocation->status !== 'applied') {
            throw new \Exception('Can only reverse applied allocations');
        }

        DB::transaction(function () use ($allocation, $reason) {
            // Mark allocation as reversed
            $allocation->update([
                'status' => 'reversed',
                'reversed_at' => now(),
                'reversal_reason' => $reason
            ]);

            // Reverse payment amounts
            $payment = $allocation->customerPaymentReceive;
            $payment->decrement('allocated_amount', $allocation->allocated_amount);
            $payment->increment('unallocated_amount', $allocation->allocated_amount);

            // Reverse sale payment amounts
            $sale = $allocation->sale;
            $sale->decrement('paid_amount', $allocation->allocated_amount);
            $sale->increment('outstanding_amount', $allocation->allocated_amount);

            // Update sale payment status
            $this->updateSalePaymentStatus($sale);

            // Update customer AR balance
            $customer = $allocation->customer;
            $customer->increment('current_ar_balance', $allocation->allocated_amount);
        });
    }

    /**
     * Recalculate allocations when payment amount changes
     */
    public function recalculateAllocations(CustomerPaymentReceive $payment): void
    {
        $totalAllocated = $payment->allocations()->where('status', '!=', 'cancelled')->sum('allocated_amount');
        $newUnallocated = $payment->total_amount - $totalAllocated;

        // If new amount is less than allocated, we need to adjust allocations
        if ($newUnallocated < 0) {
            $excessAmount = abs($newUnallocated);
            $this->reduceAllocations($payment, $excessAmount);
        }

        // Update payment amounts
        $payment->update([
            'allocated_amount' => $payment->allocations()->where('status', '!=', 'cancelled')->sum('allocated_amount'),
            'unallocated_amount' => max(0, $payment->total_amount - $payment->allocated_amount)
        ]);
    }

    /**
     * Reduce allocations when payment amount decreases
     */
    private function reduceAllocations(CustomerPaymentReceive $payment, float $reductionAmount): void
    {
        $allocations = $payment->allocations()
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        $remainingReduction = $reductionAmount;

        foreach ($allocations as $allocation) {
            if ($remainingReduction <= 0) {
                break;
            }

            if ($allocation->allocated_amount <= $remainingReduction) {
                // Cancel entire allocation
                $remainingReduction -= $allocation->allocated_amount;
                $allocation->update([
                    'status' => 'cancelled',
                    'reversal_reason' => 'Payment amount reduced'
                ]);

                // Update sale payment status
                $this->updateSalePaymentStatus($allocation->sale);
            } else {
                // Reduce allocation amount
                $newAmount = $allocation->allocated_amount - $remainingReduction;
                $allocation->update(['allocated_amount' => $newAmount]);

                // Update sale payment status
                $this->updateSalePaymentStatus($allocation->sale);

                $remainingReduction = 0;
            }
        }
    }

    /**
     * Update sale payment status based on paid amount
     */
    private function updateSalePaymentStatus(Sale $sale, ?float $additionalPayment = null): void
    {
        if ($additionalPayment) {
            $sale->increment('paid_amount', $additionalPayment);
            $sale->decrement('outstanding_amount', $additionalPayment);
        }

        // Refresh the model to get updated amounts
        $sale->refresh();

        $paidAmount = $sale->paid_amount;
        $totalAmount = $sale->total_amount;

        if ($paidAmount <= 0) {
            $status = 'unpaid';
        } elseif ($paidAmount >= $totalAmount) {
            $status = 'paid';
        } else {
            $status = 'partial';
        }

        $sale->update([
            'payment_status' => $status,
            'outstanding_amount' => max(0, $totalAmount - $paidAmount)
        ]);
    }

    /**
     * Get allocation suggestions for a payment
     */
    public function getAllocationSuggestions(CustomerPaymentReceive $payment): array
    {
        $suggestions = [];
        $remainingAmount = $payment->unallocated_amount;

        if ($remainingAmount <= 0) {
            return $suggestions;
        }

        // Get outstanding sales with priority scoring
        $outstandingSales = Sale::where('customer_id', $payment->customer_id)
            ->whereIn('payment_status', ['unpaid', 'partial'])
            ->where('outstanding_amount', '>', 0)
            ->get()
            ->map(function ($sale) {
                $daysOverdue = $sale->due_date ? now()->diffInDays($sale->due_date, false) : 0;
                $sale->priority_score = $this->calculatePriorityScore($sale, $daysOverdue);
                return $sale;
            })
            ->sortByDesc('priority_score');

        foreach ($outstandingSales as $sale) {
            if ($remainingAmount <= 0) {
                break;
            }

            $suggestedAmount = min($remainingAmount, $sale->outstanding_amount);
            $suggestions[] = [
                'sale_id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'due_date' => $sale->due_date,
                'outstanding_amount' => $sale->outstanding_amount,
                'suggested_amount' => $suggestedAmount,
                'priority_score' => $sale->priority_score,
                'days_overdue' => $sale->due_date ? now()->diffInDays($sale->due_date, false) : 0,
                'is_overdue' => $sale->due_date && $sale->due_date < now()
            ];

            $remainingAmount -= $suggestedAmount;
        }

        return $suggestions;
    }

    /**
     * Calculate priority score for allocation suggestions
     */
    private function calculatePriorityScore(Sale $sale, int $daysOverdue): float
    {
        $score = 0;

        // Overdue sales get higher priority
        if ($daysOverdue > 0) {
            $score += min($daysOverdue * 2, 100); // Max 100 points for overdue
        }

        // Older sales get higher priority
        $daysOld = now()->diffInDays($sale->created_at);
        $score += min($daysOld * 0.5, 50); // Max 50 points for age

        // Larger amounts get slightly higher priority
        $amountScore = min($sale->outstanding_amount / 1000000, 25); // Max 25 points for amount
        $score += $amountScore;

        return $score;
    }

    /**
     * Get payment allocation summary
     */
    public function getAllocationSummary(CustomerPaymentReceive $payment): array
    {
        $allocations = $payment->allocations()->with('sale:id,sale_number,total_amount,due_date')->get();

        return [
            'total_amount' => $payment->total_amount,
            'allocated_amount' => $payment->allocated_amount,
            'unallocated_amount' => $payment->unallocated_amount,
            'allocation_count' => $allocations->count(),
            'allocations' => $allocations->map(function ($allocation) {
                return [
                    'id' => $allocation->id,
                    'sale_number' => $allocation->sale->sale_number,
                    'allocated_amount' => $allocation->allocated_amount,
                    'allocation_type' => $allocation->allocation_type,
                    'status' => $allocation->status,
                    'allocation_date' => $allocation->allocation_date,
                    'notes' => $allocation->notes
                ];
            })
        ];
    }

    /**
     * Validate allocation before processing
     */
    public function validateAllocation(CustomerPaymentReceive $payment, int $saleId, float $amount): array
    {
        $errors = [];

        // Check if payment exists and is valid
        if (!$payment) {
            $errors[] = 'Payment not found';
            return $errors;
        }

        // Check if sale exists
        $sale = Sale::find($saleId);
        if (!$sale) {
            $errors[] = 'Sale not found';
            return $errors;
        }

        // Check if sale belongs to same customer
        if ($sale->customer_id !== $payment->customer_id) {
            $errors[] = 'Sale does not belong to the same customer';
        }

        // Check if payment has enough unallocated amount
        if ($amount > $payment->unallocated_amount) {
            $errors[] = 'Allocation amount exceeds unallocated payment amount';
        }

        // Check if sale needs payment
        if ($sale->payment_status === 'paid') {
            $errors[] = 'Sale is already fully paid';
        }

        // Check if amount is positive
        if ($amount <= 0) {
            $errors[] = 'Allocation amount must be greater than zero';
        }

        // Check if allocation already exists
        $existingAllocation = CustomerPaymentAllocation::where('customer_payment_receive_id', $payment->id)
            ->where('sale_id', $saleId)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingAllocation) {
            $errors[] = 'Payment is already allocated to this sale';
        }

        return $errors;
    }
}
