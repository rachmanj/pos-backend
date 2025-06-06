<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query();

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('is_default')) {
            $query->where('is_default', $request->boolean('is_default'));
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');

        if (in_array($sortBy, ['name', 'code', 'type', 'status', 'city', 'created_at', 'sort_order'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        // Include relationships
        $query->with(['zones' => function ($q) {
            $q->where('status', 'active')->orderBy('sort_order');
        }]);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $warehouses = $query->paginate($perPage);

        // Add computed attributes
        $warehouses->getCollection()->transform(function ($warehouse) {
            $warehouse->total_stock_value = $warehouse->getTotalStockValue();
            $warehouse->total_products = $warehouse->getTotalProducts();
            $warehouse->low_stock_count = $warehouse->getLowStockCount();
            $warehouse->active_zones_count = $warehouse->getActiveZonesCount();
            $warehouse->utilization_percentage = $warehouse->utilization_percentage;
            $warehouse->available_capacity = $warehouse->available_capacity;
            $warehouse->is_operational = $warehouse->is_operational;
            return $warehouse;
        });

        return response()->json([
            'success' => true,
            'data' => $warehouses->items(),
            'meta' => [
                'current_page' => $warehouses->currentPage(),
                'last_page' => $warehouses->lastPage(),
                'per_page' => $warehouses->perPage(),
                'total' => $warehouses->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|unique:warehouses,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:main,branch,storage,distribution',
            'status' => 'required|in:active,inactive,maintenance',

            // Location Information
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            // Contact Information
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:20',

            // Capacity Information
            'total_area' => 'nullable|numeric|min:0',
            'storage_area' => 'nullable|numeric|min:0',
            'max_capacity' => 'nullable|integer|min:0',

            // Operational Information
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'operating_days' => 'nullable|array',
            'operating_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Generate code if not provided
        if (empty($validated['code'])) {
            $validated['code'] = Warehouse::generateCode();
        }

        // Ensure only one default warehouse
        if ($validated['is_default'] ?? false) {
            Warehouse::where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse = Warehouse::create($validated);

        // Load relationships
        $warehouse->load('zones');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successfully',
            'data' => $warehouse,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load([
            'zones' => function ($q) {
                $q->orderBy('sort_order');
            },
            'stocks.product',
            'stocks.unit',
        ]);

        // Add computed attributes
        $warehouse->total_zones = $warehouse->zones->count();
        $warehouse->total_stock_value = $warehouse->getTotalStockValue();
        $warehouse->total_products = $warehouse->getTotalProducts();
        $warehouse->low_stock_count = $warehouse->getLowStockCount();
        $warehouse->active_zones_count = $warehouse->getActiveZonesCount();
        $warehouse->utilization_percentage = $warehouse->utilization_percentage;
        $warehouse->available_capacity = $warehouse->available_capacity;
        $warehouse->is_operational = $warehouse->is_operational;

        return response()->json([
            'success' => true,
            'data' => $warehouse,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouses')->ignore($warehouse->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:main,branch,storage,distribution',
            'status' => 'required|in:active,inactive,maintenance',

            // Location Information
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',

            // Contact Information
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:20',

            // Capacity Information
            'total_area' => 'nullable|numeric|min:0',
            'storage_area' => 'nullable|numeric|min:0',
            'max_capacity' => 'nullable|integer|min:0',

            // Operational Information
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
            'operating_days' => 'nullable|array',
            'operating_days.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'is_default' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        // Ensure only one default warehouse
        if ($validated['is_default'] ?? false) {
            Warehouse::where('id', '!=', $warehouse->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $warehouse->update($validated);

        // Load relationships
        $warehouse->load('zones');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated successfully',
            'data' => $warehouse,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        // Check if warehouse has stock
        if ($warehouse->stocks()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with existing stock',
            ], 422);
        }

        // Check if warehouse has pending transfers
        if (
            $warehouse->outgoingTransfers()->whereNotIn('status', ['completed', 'cancelled'])->exists() ||
            $warehouse->incomingTransfers()->whereNotIn('status', ['completed', 'cancelled'])->exists()
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete warehouse with pending transfers',
            ], 422);
        }

        // If this is the default warehouse, set another as default
        if ($warehouse->is_default) {
            $nextWarehouse = Warehouse::where('id', '!=', $warehouse->id)
                ->where('status', 'active')
                ->first();
            if ($nextWarehouse) {
                $nextWarehouse->update(['is_default' => true]);
            }
        }

        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successfully',
        ]);
    }

    public function setDefault(Warehouse $warehouse): JsonResponse
    {
        // Remove default from all other warehouses
        Warehouse::where('is_default', true)->update(['is_default' => false]);

        // Set this warehouse as default
        $warehouse->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Default warehouse updated successfully',
            'data' => $warehouse,
        ]);
    }

    public function analytics(Warehouse $warehouse): JsonResponse
    {
        $analytics = [
            'warehouse' => $warehouse,
            'total_stock_value' => $warehouse->getTotalStockValue(),
            'total_products' => $warehouse->getTotalProducts(),
            'low_stock_count' => $warehouse->getLowStockCount(),
            'active_zones_count' => $warehouse->getActiveZonesCount(),
            'utilization_percentage' => $warehouse->utilization_percentage,
            'available_capacity' => $warehouse->available_capacity,
            'is_operational' => $warehouse->is_operational,

            // Recent activity
            'recent_stock_movements' => $warehouse->stockMovements()
                ->with(['product', 'unit', 'createdBy'])
                ->latest()
                ->limit(10)
                ->get(),

            // Transfer statistics
            'outgoing_transfers_count' => $warehouse->outgoingTransfers()->count(),
            'incoming_transfers_count' => $warehouse->incomingTransfers()->count(),
            'pending_outgoing_transfers' => $warehouse->outgoingTransfers()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),
            'pending_incoming_transfers' => $warehouse->incomingTransfers()
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->count(),

            // Zone statistics
            'zones_by_type' => $warehouse->zones()
                ->selectRaw('type, COUNT(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),

            'zones_by_status' => $warehouse->zones()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status'),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    public function getActiveWarehouses(): JsonResponse
    {
        $warehouses = Warehouse::active()
            ->ordered()
            ->select(['id', 'code', 'name', 'type', 'city', 'is_default'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses,
        ]);
    }

    public function globalAnalytics(): JsonResponse
    {
        $warehouses = Warehouse::with(['stocks', 'zones'])->get();

        $analytics = [
            'total_warehouses' => $warehouses->count(),
            'active_warehouses' => $warehouses->where('status', 'active')->count(),
            'total_capacity' => $warehouses->sum('max_capacity') ?: 0,
            'used_capacity' => $warehouses->sum(function ($warehouse) {
                return $warehouse->stocks()->sum('quantity') ?: 0;
            }),
            'total_stock_value' => $warehouses->sum(function ($warehouse) {
                return $warehouse->getTotalStockValue();
            }),
            'total_zones' => $warehouses->sum(function ($warehouse) {
                return $warehouse->zones()->count();
            }),
            'total_products' => $warehouses->sum(function ($warehouse) {
                return $warehouse->getTotalProducts();
            }),
        ];

        // Calculate utilization percentage
        $analytics['utilization_percentage'] = $analytics['total_capacity'] > 0
            ? ($analytics['used_capacity'] / $analytics['total_capacity']) * 100
            : 0;

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }
}
