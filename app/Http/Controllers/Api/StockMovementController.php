<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StockMovementRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StockMovementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product', 'user']);

        // Apply filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('movement_type')) {
            $query->where('movement_type', $request->movement_type);
        }

        if ($request->has('reference_type')) {
            $query->where('reference_type', $request->reference_type);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $movements = $query->paginate($perPage);

        return response()->json([
            'data' => StockMovementResource::collection($movements->items()),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'from' => $movements->firstItem(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'to' => $movements->lastItem(),
                'total' => $movements->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StockMovementRequest $request): JsonResponse
    {
        $movement = StockMovement::create(array_merge(
            $request->validated(),
            ['user_id' => Auth::id()]
        ));

        $movement->load(['product', 'user']);

        return response()->json([
            'message' => 'Stock movement recorded successfully',
            'data' => new StockMovementResource($movement)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(StockMovement $stockMovement): JsonResponse
    {
        $stockMovement->load(['product', 'user']);

        return response()->json([
            'data' => new StockMovementResource($stockMovement)
        ]);
    }

    /**
     * Get stock movements for a specific product
     */
    public function byProduct(Product $product, Request $request): JsonResponse
    {
        $query = $product->stockMovements()
            ->with(['user'])
            ->orderBy('created_at', 'desc');

        // Apply date filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = $request->get('per_page', 20);
        $movements = $query->paginate($perPage);

        return response()->json([
            'data' => StockMovementResource::collection($movements->items()),
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'current_stock' => $product->current_stock
            ],
            'meta' => [
                'current_page' => $movements->currentPage(),
                'from' => $movements->firstItem(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'to' => $movements->lastItem(),
                'total' => $movements->total(),
            ]
        ]);
    }

    /**
     * Create stock adjustment
     */
    public function adjustment(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'adjustment_quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $product = Product::find($request->product_id);
        $adjustmentQuantity = $request->adjustment_quantity;
        $movementType = $adjustmentQuantity > 0 ? 'in' : 'out';
        $quantity = abs($adjustmentQuantity);

        // Check if we have enough stock for negative adjustments
        if ($adjustmentQuantity < 0 && $product->current_stock < $quantity) {
            return response()->json([
                'message' => 'Insufficient stock for this adjustment',
                'current_stock' => $product->current_stock,
                'requested_reduction' => $quantity
            ], 422);
        }

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'unit_cost' => $product->cost_price,
            'reference_type' => 'adjustment',
            'reference_id' => null,
            'notes' => "Stock adjustment: {$request->reason}. " . ($request->notes ?? ''),
            'user_id' => Auth::id()
        ]);

        $movement->load(['product', 'user']);

        return response()->json([
            'message' => 'Stock adjustment recorded successfully',
            'data' => new StockMovementResource($movement),
            'new_stock_level' => $product->fresh()->current_stock
        ], 201);
    }

    /**
     * Bulk stock adjustment
     */
    public function bulkAdjustment(Request $request): JsonResponse
    {
        $request->validate([
            'adjustments' => 'required|array|min:1',
            'adjustments.*.product_id' => 'required|exists:products,id',
            'adjustments.*.adjustment_quantity' => 'required|integer|not_in:0',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $createdMovements = [];
        $errors = [];

        foreach ($request->adjustments as $index => $adjustment) {
            try {
                $product = Product::find($adjustment['product_id']);
                $adjustmentQuantity = $adjustment['adjustment_quantity'];
                $movementType = $adjustmentQuantity > 0 ? 'in' : 'out';
                $quantity = abs($adjustmentQuantity);

                // Check stock availability for negative adjustments
                if ($adjustmentQuantity < 0 && $product->current_stock < $quantity) {
                    $errors[] = [
                        'index' => $index,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'error' => 'Insufficient stock',
                        'current_stock' => $product->current_stock,
                        'requested_reduction' => $quantity
                    ];
                    continue;
                }

                $movement = StockMovement::create([
                    'product_id' => $product->id,
                    'movement_type' => $movementType,
                    'quantity' => $quantity,
                    'unit_cost' => $product->cost_price,
                    'reference_type' => 'bulk_adjustment',
                    'reference_id' => null,
                    'notes' => "Bulk adjustment: {$request->reason}. " . ($request->notes ?? ''),
                    'user_id' => Auth::id()
                ]);

                $createdMovements[] = $movement->id;
            } catch (\Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'product_id' => $adjustment['product_id'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'message' => 'Bulk adjustment completed',
            'successful_adjustments' => count($createdMovements),
            'failed_adjustments' => count($errors),
            'errors' => $errors
        ], count($errors) > 0 ? 207 : 201); // 207 Multi-Status if some failed
    }

    /**
     * Get stock movement statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = StockMovement::query();

        // Apply date filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $totalMovements = $query->count();
        $inMovements = $query->where('movement_type', 'in')->count();
        $outMovements = $query->where('movement_type', 'out')->count();
        $adjustments = $query->where('reference_type', 'adjustment')->count();

        $totalValueIn = StockMovement::where('movement_type', 'in')
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;

        $totalValueOut = StockMovement::where('movement_type', 'out')
            ->when($request->date_from, fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->selectRaw('SUM(quantity * unit_cost) as total')
            ->value('total') ?? 0;

        return response()->json([
            'data' => [
                'total_movements' => $totalMovements,
                'in_movements' => $inMovements,
                'out_movements' => $outMovements,
                'adjustments' => $adjustments,
                'total_value_in' => round($totalValueIn, 2),
                'total_value_out' => round($totalValueOut, 2),
                'net_value' => round($totalValueIn - $totalValueOut, 2)
            ]
        ]);
    }
}
