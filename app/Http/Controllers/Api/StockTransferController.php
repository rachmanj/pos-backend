<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\WarehouseStock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StockTransferController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with([
            'fromWarehouse:id,name,code',
            'toWarehouse:id,name,code',
            'requestedBy:id,name',
            'approvedBy:id,name',
            'shippedBy:id,name',
            'receivedBy:id,name',
            'items.product:id,name,sku'
        ]);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }

        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $transfers = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transfers->items(),
            'meta' => [
                'current_page' => $transfers->currentPage(),
                'last_page' => $transfers->lastPage(),
                'per_page' => $transfers->perPage(),
                'total' => $transfers->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_warehouse_id' => 'required|exists:warehouses,id',
            'to_warehouse_id' => 'required|exists:warehouses,id|different:from_warehouse_id',
            'reference_number' => 'nullable|string|max:50|unique:stock_transfers',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.requested_quantity' => 'required|numeric|min:0.01',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Generate reference number if not provided
            $referenceNumber = $request->reference_number ?: $this->generateReferenceNumber();

            // Validate stock availability
            foreach ($request->items as $item) {
                $stock = WarehouseStock::where('warehouse_id', $request->from_warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || ($stock->current_stock - $stock->reserved_stock) < $item['requested_quantity']) {
                    $product = Product::find($item['product_id']);
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$product->name}",
                    ], 422);
                }
            }

            // Create stock transfer
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'reference_number' => $referenceNumber,
                'status' => 'pending',
                'requested_by' => Auth::id(),
                'notes' => $request->notes,
                'total_items' => count($request->items),
                'total_quantity' => collect($request->items)->sum('requested_quantity'),
            ]);

            // Create transfer items and reserve stock
            foreach ($request->items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'notes' => $item['notes'] ?? null,
                ]);

                // Reserve stock in source warehouse
                $stock = WarehouseStock::where('warehouse_id', $request->from_warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $stock->reserved_stock += $item['requested_quantity'];
                $stock->save();
            }

            DB::commit();

            $transfer->load([
                'fromWarehouse:id,name,code',
                'toWarehouse:id,name,code',
                'requestedBy:id,name',
                'items.product:id,name,sku'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer created successfully',
                'data' => $transfer,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        $stockTransfer->load([
            'fromWarehouse:id,name,code,address,city',
            'toWarehouse:id,name,code,address,city',
            'requestedBy:id,name,email',
            'approvedBy:id,name,email',
            'shippedBy:id,name,email',
            'receivedBy:id,name,email',
            'items.product:id,name,sku,unit'
        ]);

        return response()->json([
            'success' => true,
            'data' => $stockTransfer,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function approve(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending transfers can be approved',
            ], 422);
        }

        $stockTransfer->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Stock transfer approved successfully',
            'data' => $stockTransfer->fresh(['fromWarehouse', 'toWarehouse', 'approvedBy']),
        ]);
    }

    public function ship(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved transfers can be shipped',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'shipped_at' => 'required|date',
            'tracking_number' => 'nullable|string|max:100',
            'carrier' => 'nullable|string|max:100',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.shipped_quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update transfer
            $stockTransfer->update([
                'status' => 'shipped',
                'shipped_by' => Auth::id(),
                'shipped_at' => $request->shipped_at,
                'tracking_number' => $request->tracking_number,
                'carrier' => $request->carrier,
            ]);

            // Update transfer items and adjust stock
            foreach ($request->items as $itemData) {
                $transferItem = $stockTransfer->items()
                    ->where('product_id', $itemData['product_id'])
                    ->first();

                if ($transferItem) {
                    $transferItem->update([
                        'shipped_quantity' => $itemData['shipped_quantity']
                    ]);

                    // Reduce actual stock and reserved stock in source warehouse
                    $stock = WarehouseStock::where('warehouse_id', $stockTransfer->from_warehouse_id)
                        ->where('product_id', $itemData['product_id'])
                        ->first();

                    if ($stock) {
                        $stock->current_stock -= $itemData['shipped_quantity'];
                        $stock->reserved_stock -= $transferItem->quantity;
                        $stock->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer shipped successfully',
                'data' => $stockTransfer->fresh(['fromWarehouse', 'toWarehouse', 'shippedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to ship stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function receive(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if ($stockTransfer->status !== 'shipped') {
            return response()->json([
                'success' => false,
                'message' => 'Only shipped transfers can be received',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'received_at' => 'required|date',
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.received_quantity' => 'required|numeric|min:0',
            'items.*.quality_status' => ['required', Rule::in(['good', 'damaged', 'expired'])],
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update transfer
            $stockTransfer->update([
                'status' => 'completed',
                'received_by' => Auth::id(),
                'received_at' => $request->received_at,
            ]);

            // Update transfer items and add stock to destination
            foreach ($request->items as $itemData) {
                $transferItem = $stockTransfer->items()
                    ->where('product_id', $itemData['product_id'])
                    ->first();

                if ($transferItem) {
                    $transferItem->update([
                        'received_quantity' => $itemData['received_quantity'],
                        'quality_status' => $itemData['quality_status'],
                        'variance' => $itemData['received_quantity'] - $transferItem->shipped_quantity,
                        'notes' => $itemData['notes'],
                    ]);

                    // Add stock to destination warehouse (only if quality is good)
                    if ($itemData['quality_status'] === 'good') {
                        $destinationStock = WarehouseStock::firstOrCreate([
                            'warehouse_id' => $stockTransfer->to_warehouse_id,
                            'product_id' => $itemData['product_id'],
                        ], [
                            'current_stock' => 0,
                            'reserved_stock' => 0,
                            'reorder_level' => 0,
                        ]);

                        $destinationStock->current_stock += $itemData['received_quantity'];
                        $destinationStock->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer received successfully',
                'data' => $stockTransfer->fresh(['fromWarehouse', 'toWarehouse', 'receivedBy']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to receive stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, StockTransfer $stockTransfer): JsonResponse
    {
        if (!in_array($stockTransfer->status, ['pending', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or approved transfers can be rejected',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update transfer status
            $stockTransfer->update([
                'status' => 'rejected',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Release reserved stock
            foreach ($stockTransfer->items as $item) {
                $stock = WarehouseStock::where('warehouse_id', $stockTransfer->from_warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $stock->reserved_stock -= $item->quantity;
                    $stock->save();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transfer rejected successfully',
                'data' => $stockTransfer->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject stock transfer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function analytics(): JsonResponse
    {
        $analytics = [
            'total_transfers' => StockTransfer::count(),
            'pending_transfers' => StockTransfer::where('status', 'pending')->count(),
            'approved_transfers' => StockTransfer::where('status', 'approved')->count(),
            'shipped_transfers' => StockTransfer::where('status', 'shipped')->count(),
            'completed_transfers' => StockTransfer::where('status', 'completed')->count(),
            'rejected_transfers' => StockTransfer::where('status', 'rejected')->count(),
            'transfers_this_month' => StockTransfer::whereMonth('created_at', now()->month)->count(),
            'total_quantity_transferred' => StockTransfer::where('status', 'completed')->sum('total_quantity'),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    private function generateReferenceNumber(): string
    {
        $prefix = 'ST';
        $date = now()->format('Ymd');
        $sequence = StockTransfer::whereDate('created_at', now())->count() + 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }
}
