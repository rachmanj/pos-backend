<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseZone;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WarehouseZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WarehouseZone::with(['warehouse:id,name,code']);

        // Apply filters
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('zone_type')) {
            $query->where('type', $request->zone_type); // Map zone_type to type
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');

        // Map zone_type to type for sorting
        if ($sortBy === 'zone_type') {
            $sortBy = 'type';
        }

        if (in_array($sortBy, ['name', 'code', 'type', 'status', 'created_at', 'sort_order'])) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $zones = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $zones->items(),
            'meta' => [
                'current_page' => $zones->currentPage(),
                'last_page' => $zones->lastPage(),
                'per_page' => $zones->perPage(),
                'total' => $zones->total(),
            ],
        ]);
    }

    /**
     * Get zones for a specific warehouse.
     */
    public function byWarehouse(Warehouse $warehouse): JsonResponse
    {
        $zones = $warehouse->zones()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|exists:warehouses,id',
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'zone_type' => 'required|in:general,cold,frozen,hazmat,bulk,picking,staging,receiving',
            'status' => 'required|in:active,inactive,maintenance',
            'capacity_cubic_meters' => 'nullable|numeric|min:0',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric|gte:temperature_min',
            'humidity_min' => 'nullable|numeric|min:0|max:100',
            'humidity_max' => 'nullable|numeric|min:0|max:100|gte:humidity_min',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Map frontend field names to database field names
        $dbData = [
            'warehouse_id' => $validated['warehouse_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['zone_type'], // Map zone_type to type
            'status' => $validated['status'],
            'max_capacity' => $validated['capacity_cubic_meters'] ?? null, // Map capacity_cubic_meters to max_capacity
            'min_temperature' => $validated['temperature_min'] ?? null,
            'max_temperature' => $validated['temperature_max'] ?? null,
            'min_humidity' => $validated['humidity_min'] ?? null,
            'max_humidity' => $validated['humidity_max'] ?? null,
            'sort_order' => $validated['sort_order'] ?? null,
        ];

        // Generate code if not provided
        if (empty($validated['code'])) {
            $dbData['code'] = $this->generateZoneCode($validated['warehouse_id']);
        } else {
            $dbData['code'] = $validated['code'];
        }

        // Set default sort order
        if (!isset($dbData['sort_order'])) {
            $maxSort = WarehouseZone::where('warehouse_id', $validated['warehouse_id'])->max('sort_order') ?? 0;
            $dbData['sort_order'] = $maxSort + 1;
        }

        $zone = WarehouseZone::create($dbData);
        $zone->load('warehouse:id,name,code');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse zone created successfully',
            'data' => $zone,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(WarehouseZone $warehouseZone): JsonResponse
    {
        $warehouseZone->load([
            'warehouse:id,name,code',
            'stocks.product:id,name,sku',
        ]);

        // Add computed attributes that are not accessors
        $warehouseZone->total_stock_value = $warehouseZone->getTotalStockValue();
        $warehouseZone->total_products = $warehouseZone->getTotalProducts();

        return response()->json([
            'success' => true,
            'data' => $warehouseZone,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WarehouseZone $warehouseZone): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['nullable', 'string', 'max:50', Rule::unique('warehouse_zones')->ignore($warehouseZone->id)],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'zone_type' => 'required|in:general,cold,frozen,hazmat,bulk,picking,staging,receiving',
            'status' => 'required|in:active,inactive,maintenance',
            'capacity_cubic_meters' => 'nullable|numeric|min:0',
            'temperature_min' => 'nullable|numeric',
            'temperature_max' => 'nullable|numeric|gte:temperature_min',
            'humidity_min' => 'nullable|numeric|min:0|max:100',
            'humidity_max' => 'nullable|numeric|min:0|max:100|gte:humidity_min',
            'sort_order' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Map frontend field names to database field names
        $dbData = [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'type' => $validated['zone_type'], // Map zone_type to type
            'status' => $validated['status'],
            'max_capacity' => $validated['capacity_cubic_meters'] ?? null, // Map capacity_cubic_meters to max_capacity
            'min_temperature' => $validated['temperature_min'] ?? null,
            'max_temperature' => $validated['temperature_max'] ?? null,
            'min_humidity' => $validated['humidity_min'] ?? null,
            'max_humidity' => $validated['humidity_max'] ?? null,
            'sort_order' => $validated['sort_order'] ?? $warehouseZone->sort_order,
        ];

        if (!empty($validated['code'])) {
            $dbData['code'] = $validated['code'];
        }

        $warehouseZone->update($dbData);
        $warehouseZone->load('warehouse:id,name,code');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse zone updated successfully',
            'data' => $warehouseZone,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WarehouseZone $warehouseZone): JsonResponse
    {
        // Check if zone has stock
        if ($warehouseZone->stocks()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete zone with existing stock',
            ], 422);
        }

        $warehouseZone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse zone deleted successfully',
        ]);
    }

    /**
     * Get available zone types.
     */
    public function getZoneTypes(): JsonResponse
    {
        $zoneTypes = [
            ['value' => 'general', 'label' => 'General Storage'],
            ['value' => 'cold', 'label' => 'Cold Storage'],
            ['value' => 'frozen', 'label' => 'Frozen Storage'],
            ['value' => 'hazmat', 'label' => 'Hazardous Materials'],
            ['value' => 'bulk', 'label' => 'Bulk Storage'],
            ['value' => 'picking', 'label' => 'Picking Zone'],
            ['value' => 'staging', 'label' => 'Staging Area'],
            ['value' => 'receiving', 'label' => 'Receiving Area'],
        ];

        return response()->json([
            'success' => true,
            'data' => $zoneTypes,
        ]);
    }

    /**
     * Generate a unique zone code for the warehouse.
     */
    private function generateZoneCode(int $warehouseId): string
    {
        $warehouse = Warehouse::find($warehouseId);
        $warehouseCode = $warehouse ? $warehouse->code : 'WH';

        $counter = 1;
        do {
            $code = $warehouseCode . '-Z' . str_pad($counter, 2, '0', STR_PAD_LEFT);
            $exists = WarehouseZone::where('warehouse_id', $warehouseId)
                ->where('code', $code)
                ->exists();
            $counter++;
        } while ($exists && $counter <= 999);

        return $code;
    }
}
