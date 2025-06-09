<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PurchasePayment;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PurchasePaymentController extends Controller
{

    /**
     * Display a listing of purchase payments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = PurchasePayment::with([
                'supplier:id,name,code',
                'purchaseOrder:id,po_number,total_amount',
                'paymentMethod:id,name,type',
                'processedBy:id,name',
                'approvedBy:id,name'
            ]);

            // Apply filters
            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('payment_type')) {
                $query->where('payment_type', $request->payment_type);
            }

            if ($request->filled('payment_method_id')) {
                $query->where('payment_method_id', $request->payment_method_id);
            }

            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('payment_date', [$request->date_from, $request->date_to]);
            }

            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'payment_date');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $payments = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Purchase payments retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase payments',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created purchase payment
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'supplier_id' => 'required|exists:suppliers,id',
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
                'payment_date' => 'required|date',
                'amount' => 'required|numeric|min:0.01',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'allocations' => 'nullable|array',
                'allocations.*.purchase_order_id' => 'required|exists:purchase_orders,id',
                'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
                'allocations.*.notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Create the payment
            $payment = PurchasePayment::create([
                'supplier_id' => $request->supplier_id,
                'purchase_order_id' => $request->purchase_order_id,
                'payment_method_id' => $request->payment_method_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'reference_number' => $request->reference_number,
                'notes' => $request->notes,
                'processed_by' => auth('sanctum')->id(),
                'status' => 'pending',
            ]);

            // Handle allocations if provided
            if ($request->filled('allocations')) {
                $payment->allocateToOrders($request->allocations);
            }

            // Auto-complete if no approval required (small amounts)
            if ($request->amount <= 1000000) { // Auto-approve payments under 1M IDR
                $payment->complete();
            }

            DB::commit();

            $payment->load([
                'supplier:id,name,code',
                'purchaseOrder:id,po_number',
                'paymentMethod:id,name',
                'processedBy:id,name',
                'allocations.purchaseOrder:id,po_number'
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Purchase payment created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified purchase payment
     */
    public function show(PurchasePayment $purchasePayment): JsonResponse
    {
        try {
            $purchasePayment->load([
                'supplier:id,name,code,contact_person,phone',
                'purchaseOrder:id,po_number,total_amount,outstanding_amount',
                'paymentMethod:id,name,type',
                'processedBy:id,name',
                'approvedBy:id,name',
                'allocations.purchaseOrder:id,po_number,total_amount,outstanding_amount'
            ]);

            return response()->json([
                'success' => true,
                'data' => $purchasePayment,
                'message' => 'Purchase payment retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve purchase payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified purchase payment
     */
    public function update(Request $request, PurchasePayment $purchasePayment): JsonResponse
    {
        try {
            if (!$purchasePayment->can_be_edited) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be edited in current status'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'supplier_id' => 'sometimes|exists:suppliers,id',
                'purchase_order_id' => 'nullable|exists:purchase_orders,id',
                'payment_method_id' => 'sometimes|exists:payment_methods,id',
                'payment_date' => 'sometimes|date',
                'amount' => 'sometimes|numeric|min:0.01',
                'reference_number' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
                'allocations' => 'nullable|array',
                'allocations.*.purchase_order_id' => 'required|exists:purchase_orders,id',
                'allocations.*.allocated_amount' => 'required|numeric|min:0.01',
                'allocations.*.notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update payment
            $purchasePayment->update($request->only([
                'supplier_id',
                'purchase_order_id',
                'payment_method_id',
                'payment_date',
                'amount',
                'reference_number',
                'notes'
            ]));

            // Update allocations if provided
            if ($request->filled('allocations')) {
                $purchasePayment->allocateToOrders($request->allocations);
            }

            DB::commit();

            $purchasePayment->load([
                'supplier:id,name,code',
                'purchaseOrder:id,po_number',
                'paymentMethod:id,name',
                'allocations.purchaseOrder:id,po_number'
            ]);

            return response()->json([
                'success' => true,
                'data' => $purchasePayment,
                'message' => 'Purchase payment updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update purchase payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified purchase payment
     */
    public function destroy(PurchasePayment $purchasePayment): JsonResponse
    {
        try {
            if (!$purchasePayment->can_be_cancelled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be deleted in current status'
                ], 422);
            }

            DB::beginTransaction();

            // Cancel the payment instead of deleting
            $purchasePayment->cancel();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Purchase payment cancelled successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel purchase payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve a purchase payment
     */
    public function approve(PurchasePayment $purchasePayment): JsonResponse
    {
        try {
            if (!$purchasePayment->can_be_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment cannot be approved in current status'
                ], 422);
            }

            DB::beginTransaction();

            $purchasePayment->approve(auth('sanctum')->user());
            $purchasePayment->complete();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $purchasePayment->fresh(),
                'message' => 'Purchase payment approved and completed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve purchase payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get supplier outstanding orders for payment allocation
     */
    public function getSupplierOutstandingOrders(Supplier $supplier): JsonResponse
    {
        try {
            $orders = $supplier->purchaseOrders()
                ->whereIn('payment_status', ['unpaid', 'partial'])
                ->where('status', 'approved')
                ->with('items.product:id,name')
                ->orderBy('due_date', 'asc')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'po_number' => $order->po_number,
                        'order_date' => $order->order_date->format('Y-m-d'),
                        'due_date' => $order->due_date?->format('Y-m-d'),
                        'total_amount' => $order->total_amount,
                        'paid_amount' => $order->paid_amount,
                        'outstanding_amount' => $order->outstanding_amount,
                        'payment_status' => $order->payment_status,
                        'is_overdue' => $order->is_overdue,
                        'days_overdue' => $order->days_overdue,
                        'formatted_total' => $order->formatted_total,
                        'formatted_outstanding' => $order->formatted_outstanding_amount,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $orders,
                'message' => 'Outstanding orders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve outstanding orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics
     */
    public function getPaymentStats(Request $request): JsonResponse
    {
        try {
            $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

            $stats = [
                'total_payments' => PurchasePayment::completed()
                    ->byDateRange($startDate, $endDate)
                    ->sum('amount'),
                'payment_count' => PurchasePayment::completed()
                    ->byDateRange($startDate, $endDate)
                    ->count(),
                'pending_payments' => PurchasePayment::pending()->sum('amount'),
                'pending_count' => PurchasePayment::pending()->count(),
                'total_outstanding' => PurchaseOrder::whereIn('payment_status', ['unpaid', 'partial'])
                    ->sum('outstanding_amount'),
                'overdue_amount' => PurchaseOrder::whereIn('payment_status', ['unpaid', 'partial'])
                    ->where('due_date', '<', now())
                    ->sum('outstanding_amount'),
            ];

            // Format amounts
            foreach (['total_payments', 'pending_payments', 'total_outstanding', 'overdue_amount'] as $key) {
                $stats['formatted_' . $key] = 'Rp ' . number_format($stats[$key], 0, ',', '.');
            }

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Payment statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment methods for dropdown
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $paymentMethods = PaymentMethod::where('is_active', true)
                ->select('id', 'name', 'type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $paymentMethods,
                'message' => 'Payment methods retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get suppliers for dropdown
     */
    public function getSuppliers(): JsonResponse
    {
        try {
            $suppliers = Supplier::active()
                ->select('id', 'name', 'code', 'current_balance', 'credit_limit')
                ->orderBy('name')
                ->get()
                ->map(function ($supplier) {
                    return [
                        'id' => $supplier->id,
                        'name' => $supplier->name,
                        'code' => $supplier->code,
                        'display_name' => $supplier->display_name,
                        'current_balance' => $supplier->current_balance,
                        'formatted_balance' => $supplier->formatted_current_balance,
                        'credit_limit' => $supplier->credit_limit,
                        'formatted_credit_limit' => $supplier->formatted_credit_limit,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $suppliers,
                'message' => 'Suppliers retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve suppliers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
