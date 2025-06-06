<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'creator', 'approver']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'order_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $purchaseOrders = $query->paginate($perPage);

        return response()->json([
            'data' => PurchaseOrderResource::collection($purchaseOrders->items()),
            'meta' => [
                'current_page' => $purchaseOrders->currentPage(),
                'from' => $purchaseOrders->firstItem(),
                'last_page' => $purchaseOrders->lastPage(),
                'per_page' => $purchaseOrders->perPage(),
                'to' => $purchaseOrders->lastItem(),
                'total' => $purchaseOrders->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'created_by' => Auth::id(),
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions,
                'status' => 'draft',
            ]);

            foreach ($request->items as $itemData) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $itemData['product_id'],
                    'unit_id' => $itemData['unit_id'],
                    'quantity_ordered' => $itemData['quantity_ordered'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity_ordered'] * $itemData['unit_price'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $purchaseOrder->calculateTotals();
            $purchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order created successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'supplier',
            'creator',
            'approver',
            'items.product',
            'items.unit',
            'receipts'
        ]);

        return response()->json([
            'data' => new PurchaseOrderResource($purchaseOrder)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeEdited()) {
            return response()->json([
                'message' => 'Purchase order cannot be edited in current status'
            ], 422);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $purchaseOrder) {
            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions,
            ]);

            // Get existing item IDs
            $existingItemIds = $purchaseOrder->items->pluck('id')->toArray();
            $submittedItemIds = collect($request->items)->pluck('id')->filter()->toArray();

            // Delete items not in the submitted list
            $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);
            PurchaseOrderItem::whereIn('id', $itemsToDelete)->delete();

            // Update or create items
            foreach ($request->items as $itemData) {
                $itemData['purchase_order_id'] = $purchaseOrder->id;
                $itemData['total_price'] = $itemData['quantity_ordered'] * $itemData['unit_price'];

                if (isset($itemData['id'])) {
                    $item = PurchaseOrderItem::find($itemData['id']);
                    $item->update($itemData);
                } else {
                    PurchaseOrderItem::create($itemData);
                }
            }

            $purchaseOrder->calculateTotals();
            $purchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order updated successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeDeleted()) {
            return response()->json([
                'message' => 'Purchase order cannot be deleted in current status'
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase order deleted successfully'
        ]);
    }

    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeApproved()) {
            return response()->json([
                'message' => 'Purchase order cannot be approved in current status'
            ], 422);
        }

        $success = $purchaseOrder->approve(Auth::user());

        if ($success) {
            $purchaseOrder->load(['supplier', 'creator', 'approver']);

            return response()->json([
                'message' => 'Purchase order approved successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve purchase order'
        ], 422);
    }

    public function submitForApproval(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft purchase orders can be submitted for approval'
            ], 422);
        }

        if ($purchaseOrder->items->isEmpty()) {
            return response()->json([
                'message' => 'Purchase order must have at least one item'
            ], 422);
        }

        $purchaseOrder->update(['status' => 'pending_approval']);

        return response()->json([
            'message' => 'Purchase order submitted for approval successfully',
            'data' => new PurchaseOrderResource($purchaseOrder)
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeCancelled()) {
            return response()->json([
                'message' => 'Purchase order cannot be cancelled in current status'
            ], 422);
        }

        $success = $purchaseOrder->cancel();

        if ($success) {
            return response()->json([
                'message' => 'Purchase order cancelled successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        }

        return response()->json([
            'message' => 'Failed to cancel purchase order'
        ], 422);
    }

    public function duplicate(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $newPurchaseOrder = $purchaseOrder->replicate();
            $newPurchaseOrder->po_number = null; // Will be auto-generated
            $newPurchaseOrder->status = 'draft';
            $newPurchaseOrder->created_by = Auth::id();
            $newPurchaseOrder->approved_by = null;
            $newPurchaseOrder->approved_date = null;
            $newPurchaseOrder->order_date = now()->toDateString();
            $newPurchaseOrder->save();

            foreach ($purchaseOrder->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_order_id = $newPurchaseOrder->id;
                $newItem->quantity_received = 0;
                $newItem->save();
            }

            $newPurchaseOrder->calculateTotals();
            $newPurchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order duplicated successfully',
                'data' => new PurchaseOrderResource($newPurchaseOrder)
            ], 201);
        });
    }

    public function analytics(): JsonResponse
    {
        $totalOrders = PurchaseOrder::count();
        $pendingApproval = PurchaseOrder::where('status', 'pending_approval')->count();
        $pendingDelivery = PurchaseOrder::whereIn('status', ['approved', 'sent_to_supplier', 'partially_received'])->count();
        $totalAmount = PurchaseOrder::sum('total_amount');

        // Monthly stats (SQLite compatible)
        $monthlyStats = PurchaseOrder::selectRaw('
                CAST(strftime("%m", order_date) AS INTEGER) as month,
                COUNT(*) as orders,
                SUM(total_amount) as amount
            ')
            ->whereYear('order_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top suppliers
        $topSuppliers = PurchaseOrder::with('supplier')
            ->selectRaw('supplier_id, COUNT(*) as orders, SUM(total_amount) as amount')
            ->groupBy('supplier_id')
            ->orderBy('amount', 'desc')
            ->limit(5)
            ->get();

        // Status breakdown
        $statusBreakdown = PurchaseOrder::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $stats = [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'pending_approval' => $pendingApproval,
            'pending_delivery' => $pendingDelivery,
            'monthly_stats' => $monthlyStats,
            'top_suppliers' => $topSuppliers,
            'status_breakdown' => $statusBreakdown,
        ];

        return response()->json([
            'data' => $stats
        ]);
    }
}
