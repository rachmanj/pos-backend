<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Unit::with(['baseUnit', 'derivedUnits']);

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($request->has('base_unit_only') && $request->base_unit_only) {
            $query->whereNull('base_unit_id');
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $units = $query->paginate($perPage);

        return response()->json([
            'data' => UnitResource::collection($units->items()),
            'meta' => [
                'current_page' => $units->currentPage(),
                'from' => $units->firstItem(),
                'last_page' => $units->lastPage(),
                'per_page' => $units->perPage(),
                'to' => $units->lastItem(),
                'total' => $units->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UnitRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        $unit->load(['baseUnit', 'derivedUnits']);

        return response()->json([
            'message' => 'Unit created successfully',
            'data' => new UnitResource($unit)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit): JsonResponse
    {
        $unit->load(['baseUnit', 'derivedUnits', 'products']);

        return response()->json([
            'data' => new UnitResource($unit)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UnitRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        $unit->load(['baseUnit', 'derivedUnits']);

        return response()->json([
            'message' => 'Unit updated successfully',
            'data' => new UnitResource($unit)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        // Check if unit has products
        if ($unit->products()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete unit that is used by products. Please change the unit for those products first.'
            ], 422);
        }

        // Check if unit has derived units
        if ($unit->derivedUnits()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete unit that has derived units. Please delete or change the base unit for derived units first.'
            ], 422);
        }

        $unit->delete();

        return response()->json([
            'message' => 'Unit deleted successfully'
        ]);
    }

    /**
     * Get conversion information between units
     */
    public function conversion(Request $request): JsonResponse
    {
        $request->validate([
            'from_unit_id' => 'required|exists:units,id',
            'to_unit_id' => 'required|exists:units,id',
            'quantity' => 'required|numeric|min:0'
        ]);

        $fromUnit = Unit::find($request->from_unit_id);
        $toUnit = Unit::find($request->to_unit_id);
        $quantity = $request->quantity;

        try {
            $convertedQuantity = $fromUnit->convertTo($toUnit, $quantity);

            return response()->json([
                'data' => [
                    'from_unit' => new UnitResource($fromUnit),
                    'to_unit' => new UnitResource($toUnit),
                    'original_quantity' => $quantity,
                    'converted_quantity' => $convertedQuantity,
                    'conversion_note' => "{$quantity} {$fromUnit->symbol} = {$convertedQuantity} {$toUnit->symbol}"
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Cannot convert between these units. They may not have a common base unit.',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get base units only
     */
    public function baseUnits(): JsonResponse
    {
        $baseUnits = Unit::whereNull('base_unit_id')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UnitResource::collection($baseUnits)
        ]);
    }
}
