<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'unit', 'stock']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('low_stock') && $request->low_stock) {
            $query->withLowStock();
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $products = $query->paginate($perPage);

        return response()->json([
            'data' => ProductResource::collection($products->items()),
            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        $product->load(['category', 'unit', 'stock']);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => new ProductResource($product)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'unit', 'stock', 'stockMovements' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());
        $product->load(['category', 'unit', 'stock']);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        // Check if product has stock movements
        if ($product->stockMovements()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete product with stock movements. Archive the product instead.'
            ], 422);
        }

        // Delete associated stock record
        $product->stock()->delete();
        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Search products by query
     */
    public function search(string $query): JsonResponse
    {
        $products = Product::with(['category', 'unit', 'stock'])
            ->search($query)
            ->active()
            ->limit(50)
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products)
        ]);
    }

    /**
     * Find product by barcode
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        $product = Product::with(['category', 'unit', 'stock'])
            ->where('barcode', $barcode)
            ->first();

        if (!$product) {
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'data' => new ProductResource($product)
        ]);
    }

    /**
     * Get products with low stock
     */
    public function lowStock(): JsonResponse
    {
        $products = Product::with(['category', 'unit', 'stock'])
            ->withLowStock()
            ->active()
            ->get();

        return response()->json([
            'data' => ProductResource::collection($products)
        ]);
    }

    /**
     * Get stock history for a product
     */
    public function stockHistory(Product $product): JsonResponse
    {
        $movements = $product->stockMovements()
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $movements->items(),
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
     * Bulk update products
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'action' => 'required|in:activate,deactivate,update_prices'
        ]);

        $productIds = collect($request->products)->pluck('id');
        $action = $request->action;

        switch ($action) {
            case 'activate':
                Product::whereIn('id', $productIds)->update(['status' => 'active']);
                break;
            case 'deactivate':
                Product::whereIn('id', $productIds)->update(['status' => 'inactive']);
                break;
            case 'update_prices':
                $request->validate([
                    'price_adjustment' => 'required|numeric',
                    'adjustment_type' => 'required|in:percentage,fixed'
                ]);

                $products = Product::whereIn('id', $productIds)->get();
                foreach ($products as $product) {
                    if ($request->adjustment_type === 'percentage') {
                        $newPrice = $product->selling_price * (1 + $request->price_adjustment / 100);
                    } else {
                        $newPrice = $product->selling_price + $request->price_adjustment;
                    }
                    $product->update(['selling_price' => max(0, $newPrice)]);
                }
                break;
        }

        return response()->json([
            'message' => 'Products updated successfully',
            'affected_count' => count($productIds)
        ]);
    }

    /**
     * Get inventory dashboard data
     */
    public function dashboard(): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::active()->count();
        $lowStockProducts = Product::withLowStock()->count();
        $outOfStockProducts = ProductStock::outOfStock()->count();
        $totalValue = Product::with('stock')->get()->sum('total_value');

        return response()->json([
            'data' => [
                'total_products' => $totalProducts,
                'active_products' => $activeProducts,
                'low_stock_products' => $lowStockProducts,
                'out_of_stock_products' => $outOfStockProducts,
                'total_inventory_value' => round($totalValue, 2)
            ]
        ]);
    }

    /**
     * Get stock levels summary
     */
    public function stockLevels(): JsonResponse
    {
        $stockLevels = ProductStock::with(['product'])
            ->selectRaw('
                SUM(current_stock) as total_stock,
                SUM(reserved_stock) as total_reserved,
                SUM(available_stock) as total_available,
                COUNT(*) as total_products
            ')
            ->first();

        return response()->json([
            'data' => $stockLevels
        ]);
    }

    /**
     * Get stock alerts
     */
    public function stockAlerts(): JsonResponse
    {
        $lowStock = Product::with(['category', 'unit', 'stock'])
            ->withLowStock()
            ->get();

        $outOfStock = Product::with(['category', 'unit', 'stock'])
            ->whereHas('stock', function ($query) {
                $query->outOfStock();
            })
            ->get();

        return response()->json([
            'data' => [
                'low_stock' => ProductResource::collection($lowStock),
                'out_of_stock' => ProductResource::collection($outOfStock)
            ]
        ]);
    }

    /**
     * Get inventory valuation
     */
    public function valuation(): JsonResponse
    {
        $products = Product::with(['stock', 'category'])
            ->whereHas('stock', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            ->get();

        $totalCostValue = $products->sum(function ($product) {
            return $product->stock->current_stock * $product->cost_price;
        });

        $totalSellingValue = $products->sum(function ($product) {
            return $product->stock->current_stock * $product->selling_price;
        });

        $byCategory = $products->groupBy('category.name')->map(function ($categoryProducts) {
            return [
                'cost_value' => $categoryProducts->sum(function ($product) {
                    return $product->stock->current_stock * $product->cost_price;
                }),
                'selling_value' => $categoryProducts->sum(function ($product) {
                    return $product->stock->current_stock * $product->selling_price;
                }),
                'product_count' => $categoryProducts->count()
            ];
        });

        return response()->json([
            'data' => [
                'total_cost_value' => round($totalCostValue, 2),
                'total_selling_value' => round($totalSellingValue, 2),
                'potential_profit' => round($totalSellingValue - $totalCostValue, 2),
                'by_category' => $byCategory
            ]
        ]);
    }
}
