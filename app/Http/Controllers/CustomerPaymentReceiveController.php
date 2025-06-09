<?php

namespace App\Http\Controllers;

use App\Models\CustomerPaymentReceive;
use App\Models\CustomerPaymentAllocation;
use App\Models\CustomerCreditLimit;
use App\Models\CustomerPaymentSchedule;
use App\Models\CustomerAgingSnapshot;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\PaymentMethod;
use App\Services\PaymentAllocationService;
use App\Services\CreditManagementService;
use App\Services\ARAgingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class CustomerPaymentReceiveController extends Controller
{
    protected $paymentAllocationService;
    protected $creditManagementService;
    protected $arAgingService;

    public function __construct(
        PaymentAllocationService $paymentAllocationService,
        CreditManagementService $creditManagementService,
        ARAgingService $arAgingService
    ) {
        $this->paymentAllocationService = $paymentAllocationService;
        $this->creditManagementService = $creditManagementService;
        $this->arAgingService = $arAgingService;
    }

    /**
     * Display a listing of customer payment receives
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = CustomerPaymentReceive::with([
                'customer:id,name,customer_code,phone,email',
                'paymentMethod:id,name,type',
                'processedBy:id,name',
                'approvedBy:id,name',
                'allocations.sale:id,sale_number,total_amount',
                'allocations.allocatedBy:id,name'
            ]);

            // Apply filters
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->filled('payment_method_id')) {
                $query->where('payment_method_id', $request->payment_method_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('workflow_status')) {
                $query->where('workflow_status', $request->workflow_status);
            }

            if ($request->filled('date_from')) {
                $query->whereDate('payment_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('payment_date', '<=', $request->date_to);
            }

            if ($request->filled('amount_from')) {
                $query->where('total_amount', '>=', $request->amount_from);
            }

            if ($request->filled('amount_to')) {
                $query->where('total_amount', '<=', $request->amount_to);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('payment_number', 'like', "%{$search}%")
                        ->orWhere('reference_number', 'like', "%{$search}%")
                        ->orWhere('bank_reference', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            }

            // Sorting
            $sortField = $request->get('sort_field', 'payment_date');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $payments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'summary' => [
                    'total_payments' => $payments->total(),
                    'total_amount' => CustomerPaymentReceive::sum('total_amount'),
                    'allocated_amount' => CustomerPaymentReceive::sum('allocated_amount'),
                    'unallocated_amount' => CustomerPaymentReceive::sum('unallocated_amount'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment receives',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created payment receive
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'payment_method_id' => 'required|exists:payment_methods,id',
            'total_amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'bank_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'auto_allocate' => 'boolean',
            'allocations' => 'nullable|array',
            'allocations.*.sale_id' => 'required_with:allocations|exists:sales,id',
            'allocations.*.allocated_amount' => 'required_with:allocations|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Generate payment number
            $paymentNumber = $this->generatePaymentNumber();

            // Create payment receive
            $payment = CustomerPaymentReceive::create([
                'payment_number' => $paymentNumber,
                'customer_id' => $request->customer_id,
                'payment_method_id' => $request->payment_method_id,
                'total_amount' => $request->total_amount,
                'allocated_amount' => 0,
                'unallocated_amount' => $request->total_amount,
                'payment_date' => $request->payment_date,
                'reference_number' => $request->reference_number,
                'bank_reference' => $request->bank_reference,
                'status' => 'pending',
                'workflow_status' => 'pending_verification',
                'processed_by' => Auth::id(),
                'notes' => $request->notes,
                'requires_approval' => $request->total_amount > 10000000, // 10M IDR threshold
                'metadata' => [
                    'created_via' => 'api',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);

            // Handle allocations if provided
            if ($request->filled('allocations')) {
                foreach ($request->allocations as $allocationData) {
                    $this->paymentAllocationService->allocatePayment(
                        $payment,
                        $allocationData['sale_id'],
                        $allocationData['allocated_amount'],
                        'manual',
                        $allocationData['notes'] ?? null
                    );
                }
            } elseif ($request->get('auto_allocate', false)) {
                // Auto-allocate to oldest outstanding sales
                $this->paymentAllocationService->autoAllocatePayment($payment);
            }

            // Update customer AR balance
            $this->creditManagementService->updateCustomerBalance($request->customer_id);

            // Refresh aging data
            $this->arAgingService->updateCustomerAging($request->customer_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment receive created successfully',
                'data' => $payment->load([
                    'customer:id,name,customer_code',
                    'paymentMethod:id,name,type',
                    'allocations.sale:id,sale_number,total_amount'
                ])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment receive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified payment receive
     */
    public function show(CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        try {
            $payment = $customerPaymentReceive->load([
                'customer:id,name,customer_code,phone,email,current_ar_balance',
                'paymentMethod:id,name,type',
                'processedBy:id,name,email',
                'approvedBy:id,name,email',
                'verifiedBy:id,name,email',
                'allocations' => function ($query) {
                    $query->with([
                        'sale:id,sale_number,total_amount,payment_status,due_date',
                        'allocatedBy:id,name',
                        'approvedBy:id,name'
                    ]);
                }
            ]);

            // Get customer outstanding sales
            $outstandingSales = Sale::where('customer_id', $payment->customer_id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->with('items:id,sale_id,product_name,quantity,unit_price,total_price')
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'outstanding_sales' => $outstandingSales,
                    'allocation_summary' => [
                        'total_amount' => $payment->total_amount,
                        'allocated_amount' => $payment->allocated_amount,
                        'unallocated_amount' => $payment->unallocated_amount,
                        'allocation_count' => $payment->allocations->count()
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment receive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified payment receive
     */
    public function update(Request $request, CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_method_id' => 'sometimes|exists:payment_methods,id',
            'total_amount' => 'sometimes|numeric|min:0.01',
            'payment_date' => 'sometimes|date',
            'reference_number' => 'nullable|string|max:100',
            'bank_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'status' => 'sometimes|in:pending,verified,allocated,completed,cancelled',
            'workflow_status' => 'sometimes|in:pending_verification,verified,pending_approval,approved,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if payment can be updated
        if (in_array($customerPaymentReceive->status, ['completed', 'cancelled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update completed or cancelled payment'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $oldAmount = $customerPaymentReceive->total_amount;
            $customerPaymentReceive->update($request->only([
                'payment_method_id',
                'total_amount',
                'payment_date',
                'reference_number',
                'bank_reference',
                'notes',
                'status',
                'workflow_status'
            ]));

            // If amount changed, recalculate allocations
            if ($request->filled('total_amount') && $request->total_amount != $oldAmount) {
                $this->paymentAllocationService->recalculateAllocations($customerPaymentReceive);
            }

            // Update customer balance
            $this->creditManagementService->updateCustomerBalance($customerPaymentReceive->customer_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment receive updated successfully',
                'data' => $customerPaymentReceive->fresh([
                    'customer:id,name,customer_code',
                    'paymentMethod:id,name,type',
                    'allocations.sale:id,sale_number,total_amount'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment receive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified payment receive
     */
    public function destroy(CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        // Check if payment can be deleted
        if (in_array($customerPaymentReceive->status, ['completed', 'allocated'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete completed or allocated payment'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $customerId = $customerPaymentReceive->customer_id;

            // Delete allocations first
            $customerPaymentReceive->allocations()->delete();

            // Delete payment
            $customerPaymentReceive->delete();

            // Update customer balance
            $this->creditManagementService->updateCustomerBalance($customerId);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment receive deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payment receive',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment receive
     */
    public function verify(Request $request, CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        if ($customerPaymentReceive->workflow_status !== 'pending_verification') {
            return response()->json([
                'success' => false,
                'message' => 'Payment is not in pending verification status'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $customerPaymentReceive->update([
                'workflow_status' => $customerPaymentReceive->requires_approval ? 'pending_approval' : 'verified',
                'status' => 'verified',
                'verified_by' => Auth::id(),
                'verified_at' => now(),
                'verification_notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment verified successfully',
                'data' => $customerPaymentReceive->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve payment receive
     */
    public function approve(Request $request, CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        if ($customerPaymentReceive->workflow_status !== 'pending_approval') {
            return response()->json([
                'success' => false,
                'message' => 'Payment is not in pending approval status'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $customerPaymentReceive->update([
                'workflow_status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment approved successfully',
                'data' => $customerPaymentReceive->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject payment receive
     */
    public function reject(Request $request, CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection reason is required',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $customerPaymentReceive->update([
                'workflow_status' => 'rejected',
                'status' => 'cancelled',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment rejected successfully',
                'data' => $customerPaymentReceive->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Allocate payment to sales
     */
    public function allocatePayment(Request $request, CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'allocations' => 'required|array|min:1',
            'allocations.*.sale_id' => 'required|exists:sales,id',
            'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
            'allocations.*.notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $totalAllocated = 0;

            foreach ($request->allocations as $allocationData) {
                $allocation = $this->paymentAllocationService->allocatePayment(
                    $customerPaymentReceive,
                    $allocationData['sale_id'],
                    $allocationData['allocated_amount'],
                    'manual',
                    $allocationData['notes'] ?? null
                );

                $totalAllocated += $allocationData['allocated_amount'];
            }

            // Update payment status
            if ($customerPaymentReceive->unallocated_amount <= 0) {
                $customerPaymentReceive->update([
                    'status' => 'completed',
                    'workflow_status' => 'completed'
                ]);
            } else {
                $customerPaymentReceive->update([
                    'status' => 'allocated'
                ]);
            }

            // Update customer balance
            $this->creditManagementService->updateCustomerBalance($customerPaymentReceive->customer_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment allocated successfully',
                'data' => [
                    'payment' => $customerPaymentReceive->fresh(),
                    'total_allocated' => $totalAllocated,
                    'remaining_amount' => $customerPaymentReceive->unallocated_amount
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Auto-allocate payment to oldest outstanding sales
     */
    public function autoAllocate(CustomerPaymentReceive $customerPaymentReceive): JsonResponse
    {
        if ($customerPaymentReceive->unallocated_amount <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No unallocated amount available'
            ], 422);
        }

        DB::beginTransaction();
        try {
            $allocations = $this->paymentAllocationService->autoAllocatePayment($customerPaymentReceive);

            // Update customer balance
            $this->creditManagementService->updateCustomerBalance($customerPaymentReceive->customer_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment auto-allocated successfully',
                'data' => [
                    'payment' => $customerPaymentReceive->fresh(),
                    'allocations' => $allocations,
                    'allocation_count' => count($allocations)
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto-allocate payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer outstanding balance and sales
     */
    public function getCustomerOutstanding(Customer $customer): JsonResponse
    {
        try {
            $outstandingSales = Sale::where('customer_id', $customer->id)
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->with([
                    'items:id,sale_id,product_name,quantity,unit_price,total_price',
                    'warehouse:id,name'
                ])
                ->orderBy('due_date', 'asc')
                ->get();

            $agingData = $this->arAgingService->getCustomerAging($customer->id);
            $creditLimit = $this->creditManagementService->getCustomerCreditLimit($customer->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'customer' => $customer->load('creditLimit'),
                    'outstanding_sales' => $outstandingSales,
                    'aging_analysis' => $agingData,
                    'credit_limit' => $creditLimit,
                    'summary' => [
                        'total_outstanding' => $outstandingSales->sum('outstanding_amount'),
                        'overdue_amount' => $outstandingSales->where('due_date', '<', now())->sum('outstanding_amount'),
                        'sales_count' => $outstandingSales->count(),
                        'oldest_due_date' => $outstandingSales->min('due_date')
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve customer outstanding',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AR aging report
     */
    public function getAgingReport(Request $request): JsonResponse
    {
        try {
            $agingData = $this->arAgingService->generateAgingReport($request->all());

            return response()->json([
                'success' => true,
                'data' => $agingData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate aging report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment dashboard data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', now()->startOfMonth());
            $dateTo = $request->get('date_to', now()->endOfMonth());

            $data = [
                'summary' => [
                    'total_payments' => CustomerPaymentReceive::whereBetween('payment_date', [$dateFrom, $dateTo])->count(),
                    'total_amount' => CustomerPaymentReceive::whereBetween('payment_date', [$dateFrom, $dateTo])->sum('total_amount'),
                    'allocated_amount' => CustomerPaymentReceive::whereBetween('payment_date', [$dateFrom, $dateTo])->sum('allocated_amount'),
                    'unallocated_amount' => CustomerPaymentReceive::whereBetween('payment_date', [$dateFrom, $dateTo])->sum('unallocated_amount'),
                    'pending_verification' => CustomerPaymentReceive::where('workflow_status', 'pending_verification')->count(),
                    'pending_approval' => CustomerPaymentReceive::where('workflow_status', 'pending_approval')->count()
                ],
                'recent_payments' => CustomerPaymentReceive::with([
                    'customer:id,name,customer_code',
                    'paymentMethod:id,name,type'
                ])->latest('payment_date')->limit(10)->get(),
                'aging_summary' => $this->arAgingService->getAgingSummary(),
                'payment_methods' => CustomerPaymentReceive::selectRaw('payment_method_id, COUNT(*) as count, SUM(total_amount) as total')
                    ->with('paymentMethod:id,name,type')
                    ->whereBetween('payment_date', [$dateFrom, $dateTo])
                    ->groupBy('payment_method_id')
                    ->get()
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate unique payment number
     */
    private function generatePaymentNumber(): string
    {
        $prefix = 'PAY';
        $date = now()->format('Ymd');
        $lastPayment = CustomerPaymentReceive::whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastPayment ? (int)substr($lastPayment->payment_number, -4) + 1 : 1;

        return $prefix . $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
