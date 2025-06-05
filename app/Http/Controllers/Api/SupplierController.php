<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $suppliers = $query->paginate($perPage);

        return response()->json([
            'data' => SupplierResource::collection($suppliers->items()),
            'meta' => [
                'current_page' => $suppliers->currentPage(),
                'from' => $suppliers->firstItem(),
                'last_page' => $suppliers->lastPage(),
                'per_page' => $suppliers->perPage(),
                'to' => $suppliers->lastItem(),
                'total' => $suppliers->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return response()->json([
            'message' => 'Supplier created successfully',
            'data' => new SupplierResource($supplier)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        return response()->json([
            'data' => new SupplierResource($supplier)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Supplier updated successfully',
            'data' => new SupplierResource($supplier)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return response()->json([
            'message' => 'Supplier deleted successfully'
        ]);
    }

    /**
     * Search suppliers
     */
    public function search(string $query): JsonResponse
    {
        $suppliers = Supplier::where('name', 'like', "%{$query}%")
            ->orWhere('contact_person', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->active()
            ->limit(20)
            ->get();

        return response()->json([
            'data' => SupplierResource::collection($suppliers)
        ]);
    }

    /**
     * Get supplier performance metrics
     */
    public function performance(Supplier $supplier): JsonResponse
    {
        // This would typically calculate metrics from purchase orders and receipts
        // For now, we'll return basic information
        return response()->json([
            'data' => [
                'supplier' => new SupplierResource($supplier),
                'metrics' => [
                    'total_orders' => 0, // Would calculate from purchase_orders table
                    'total_value' => 0,  // Would calculate from purchase_orders table
                    'on_time_delivery_rate' => 0, // Would calculate based on delivery dates
                    'quality_rating' => 0, // Would be stored separately
                    'last_order_date' => null, // Would get from latest purchase order
                ]
            ]
        ]);
    }

    /**
     * Get active suppliers only
     */
    public function active(): JsonResponse
    {
        $suppliers = Supplier::active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => SupplierResource::collection($suppliers)
        ]);
    }
}
