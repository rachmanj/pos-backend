<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PurchaseReceiptController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseReceipt::with(['purchaseOrder.supplier', 'receiver']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('purchase_order_id')) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('date_from')) {
            $query->where('receipt_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('receipt_date', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'receipt_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $receipts = $query->paginate($perPage);

        return response()->json([
            'data' => PurchaseReceiptResource::collection($receipts->items()),
            'meta' => [
                'current_page' => $receipts->currentPage(),
                'from' => $receipts->firstItem(),
                'last_page' => $receipts->lastPage(),
                'per_page' => $receipts->perPage(),
                'to' => $receipts->lastItem(),
                'total' => $receipts->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'purchase_order_id' => 'required|exists:purchase_orders,id',
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'required|numeric|min:0',
            'items.*.quality_notes' => 'nullable|string',
            'items.*.rejection_reason' => 'nullable|string',
        ]);

        // Validate that accepted + rejected = received for each item
        foreach ($request->items as $itemData) {
            $total = $itemData['quantity_accepted'] + $itemData['quantity_rejected'];
            if (abs($total - $itemData['quantity_received']) > 0.001) {
                return response()->json([
                    'message' => 'Quantity accepted + rejected must equal quantity received for all items'
                ], 422);
            }
        }

        return DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::find($request->purchase_order_id);

            $receipt = PurchaseReceipt::create([
                'purchase_order_id' => $request->purchase_order_id,
                'received_by' => Auth::id(),
                'receipt_date' => $request->receipt_date,
                'notes' => $request->notes,
                'status' => 'draft',
            ]);

            foreach ($request->items as $itemData) {
                $purchaseOrderItem = $purchaseOrder->items()->find($itemData['purchase_order_item_id']);

                PurchaseReceiptItem::create([
                    'purchase_receipt_id' => $receipt->id,
                    'purchase_order_item_id' => $itemData['purchase_order_item_id'],
                    'product_id' => $purchaseOrderItem->product_id,
                    'unit_id' => $purchaseOrderItem->unit_id,
                    'quantity_received' => $itemData['quantity_received'],
                    'quantity_accepted' => $itemData['quantity_accepted'],
                    'quantity_rejected' => $itemData['quantity_rejected'],
                    'quality_notes' => $itemData['quality_notes'] ?? null,
                    'rejection_reason' => $itemData['rejection_reason'] ?? null,
                ]);
            }

            $receipt->load([
                'purchaseOrder.supplier',
                'receiver',
                'items.product',
                'items.unit',
                'items.purchaseOrderItem'
            ]);

            return response()->json([
                'message' => 'Purchase receipt created successfully',
                'data' => new PurchaseReceiptResource($receipt)
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseReceipt $purchaseReceipt): JsonResponse
    {
        $purchaseReceipt->load([
            'purchaseOrder.supplier',
            'receiver',
            'items.product',
            'items.unit',
            'items.purchaseOrderItem'
        ]);

        return response()->json([
            'data' => new PurchaseReceiptResource($purchaseReceipt)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseReceipt $purchaseReceipt): JsonResponse
    {
        if (!$purchaseReceipt->canBeEdited()) {
            return response()->json([
                'message' => 'Purchase receipt cannot be edited in current status'
            ], 422);
        }

        $request->validate([
            'receipt_date' => 'required|date',
            'notes' => 'nullable|string',
            'quality_check_notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|exists:purchase_receipt_items,id',
            'items.*.quantity_received' => 'required|numeric|min:0.001',
            'items.*.quantity_accepted' => 'required|numeric|min:0',
            'items.*.quantity_rejected' => 'required|numeric|min:0',
            'items.*.quality_notes' => 'nullable|string',
            'items.*.rejection_reason' => 'nullable|string',
        ]);

        // Validate quantities
        foreach ($request->items as $itemData) {
            $total = $itemData['quantity_accepted'] + $itemData['quantity_rejected'];
            if (abs($total - $itemData['quantity_received']) > 0.001) {
                return response()->json([
                    'message' => 'Quantity accepted + rejected must equal quantity received for all items'
                ], 422);
            }
        }

        return DB::transaction(function () use ($request, $purchaseReceipt) {
            $purchaseReceipt->update([
                'receipt_date' => $request->receipt_date,
                'notes' => $request->notes,
                'quality_check_notes' => $request->quality_check_notes,
            ]);

            foreach ($request->items as $itemData) {
                $item = PurchaseReceiptItem::find($itemData['id']);
                $item->update([
                    'quantity_received' => $itemData['quantity_received'],
                    'quantity_accepted' => $itemData['quantity_accepted'],
                    'quantity_rejected' => $itemData['quantity_rejected'],
                    'quality_notes' => $itemData['quality_notes'],
                    'rejection_reason' => $itemData['rejection_reason'],
                ]);
            }

            $purchaseReceipt->load([
                'purchaseOrder.supplier',
                'receiver',
                'items.product',
                'items.unit',
                'items.purchaseOrderItem'
            ]);

            return response()->json([
                'message' => 'Purchase receipt updated successfully',
                'data' => new PurchaseReceiptResource($purchaseReceipt)
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseReceipt $purchaseReceipt): JsonResponse
    {
        if (!$purchaseReceipt->canBeEdited()) {
            return response()->json([
                'message' => 'Purchase receipt cannot be deleted in current status'
            ], 422);
        }

        $purchaseReceipt->delete();

        return response()->json([
            'message' => 'Purchase receipt deleted successfully'
        ]);
    }

    public function approve(PurchaseReceipt $purchaseReceipt): JsonResponse
    {
        if ($purchaseReceipt->status !== 'complete') {
            return response()->json([
                'message' => 'Only complete purchase receipts can be approved'
            ], 422);
        }

        $success = $purchaseReceipt->approve();

        if ($success) {
            $purchaseReceipt->load([
                'purchaseOrder.supplier',
                'receiver',
                'items'
            ]);

            return response()->json([
                'message' => 'Purchase receipt approved and stock updated successfully',
                'data' => new PurchaseReceiptResource($purchaseReceipt)
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve purchase receipt'
        ], 422);
    }

    public function updateStock(PurchaseReceipt $purchaseReceipt): JsonResponse
    {
        if (!$purchaseReceipt->canUpdateStock()) {
            return response()->json([
                'message' => 'Stock cannot be updated for this receipt'
            ], 422);
        }

        $success = $purchaseReceipt->updateStock();

        if ($success) {
            return response()->json([
                'message' => 'Stock updated successfully',
                'data' => new PurchaseReceiptResource($purchaseReceipt)
            ]);
        }

        return response()->json([
            'message' => 'Failed to update stock'
        ], 422);
    }

    public function getReceivableItems(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load(['items.product', 'items.unit']);

        $receivableItems = $purchaseOrder->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'sku' => $item->product->sku,
                ],
                'unit' => [
                    'id' => $item->unit->id,
                    'name' => $item->unit->name,
                    'symbol' => $item->unit->symbol,
                ],
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'remaining_quantity' => $item->remaining_quantity,
                'unit_price' => $item->unit_price,
                'can_receive' => $item->remaining_quantity > 0,
            ];
        });

        return response()->json([
            'data' => $receivableItems
        ]);
    }

    public function analytics(): JsonResponse
    {
        $stats = [
            'total_receipts' => PurchaseReceipt::count(),
            'draft_receipts' => PurchaseReceipt::where('status', 'draft')->count(),
            'pending_quality_check' => PurchaseReceipt::where('status', 'quality_check_pending')->count(),
            'completed_receipts' => PurchaseReceipt::where('status', 'complete')->count(),
            'approved_receipts' => PurchaseReceipt::where('status', 'approved')->count(),
            'stock_updated_receipts' => PurchaseReceipt::where('stock_updated', true)->count(),
        ];

        return response()->json([
            'data' => $stats
        ]);
    }
}
