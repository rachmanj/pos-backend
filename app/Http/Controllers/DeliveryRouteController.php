<?php

namespace App\Http\Controllers;

use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteStop;
use App\Models\DeliveryOrder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DeliveryRouteController extends Controller
{
    /**
     * Display a listing of delivery routes with filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DeliveryRoute::with([
                'driver:id,name,phone',
                'deliveryRouteStops.deliveryOrder.salesOrder.customer:id,name,customer_code'
            ]);

            // Filtering
            if ($request->filled('driver_id')) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->filled('route_status')) {
                $statuses = is_array($request->route_status) ? $request->route_status : [$request->route_status];
                $query->whereIn('route_status', $statuses);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('route_name', 'like', "%{$search}%")
                        ->orWhere('vehicle_id', 'like', "%{$search}%")
                        ->orWhereHas('driver', function ($driverQuery) use ($search) {
                            $driverQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Date filtering
            if ($request->filled('date_from')) {
                $query->whereDate('route_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('route_date', '<=', $request->date_to);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $routes = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $routes,
                'filters_applied' => $request->only([
                    'driver_id',
                    'route_status',
                    'search',
                    'date_from',
                    'date_to'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery routes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created delivery route
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'route_name' => 'required|string|max:255',
            'route_date' => 'required|date|after_or_equal:today',
            'driver_id' => 'required|exists:users,id',
            'vehicle_id' => 'nullable|string|max:100',
            'start_time' => 'nullable|date_format:H:i',
            'delivery_order_ids' => 'required|array|min:1',
            'delivery_order_ids.*' => 'exists:delivery_orders,id'
        ]);

        DB::beginTransaction();

        try {
            // Validate delivery orders
            $deliveryOrders = DeliveryOrder::whereIn('id', $request->delivery_order_ids)
                ->where('delivery_status', 'pending')
                ->get();

            if ($deliveryOrders->count() !== count($request->delivery_order_ids)) {
                throw new \Exception('Some delivery orders are not available for routing');
            }

            // Check if any delivery orders are already assigned to routes
            $assignedOrders = DeliveryRouteStop::whereIn('delivery_order_id', $request->delivery_order_ids)
                ->whereHas('deliveryRoute', function ($query) {
                    $query->where('route_status', '!=', 'completed');
                })
                ->count();

            if ($assignedOrders > 0) {
                throw new \Exception('Some delivery orders are already assigned to active routes');
            }

            // Create delivery route
            $route = DeliveryRoute::create([
                'route_name' => $request->route_name,
                'route_date' => $request->route_date,
                'driver_id' => $request->driver_id,
                'vehicle_id' => $request->vehicle_id,
                'start_time' => $request->start_time,
                'route_status' => 'planned',
                'total_distance' => 0,
                'estimated_duration' => 0
            ]);

            // Create route stops in sequence
            $stopSequence = 1;
            $totalEstimatedDuration = 0;

            foreach ($deliveryOrders as $deliveryOrder) {
                DeliveryRouteStop::create([
                    'delivery_route_id' => $route->id,
                    'delivery_order_id' => $deliveryOrder->id,
                    'stop_sequence' => $stopSequence,
                    'estimated_arrival' => null, // To be calculated by route optimization
                    'stop_status' => 'pending'
                ]);

                $stopSequence++;
                $totalEstimatedDuration += 30; // Estimate 30 minutes per stop
            }

            // Update route with estimated duration
            $route->update([
                'estimated_duration' => $totalEstimatedDuration
            ]);

            DB::commit();

            // Load relationships for response
            $route->load([
                'driver:id,name,phone',
                'deliveryRouteStops.deliveryOrder.salesOrder.customer:id,name,customer_code'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery route created successfully',
                'data' => $route
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery route',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified delivery route
     */
    public function show(string $id): JsonResponse
    {
        try {
            $route = DeliveryRoute::with([
                'driver:id,name,phone,email',
                'deliveryRouteStops' => function ($query) {
                    $query->orderBy('stop_sequence')
                        ->with([
                            'deliveryOrder' => function ($q) {
                                $q->with([
                                    'salesOrder.customer:id,name,customer_code,phone',
                                    'deliveryOrderItems.product:id,name,sku'
                                ]);
                            }
                        ]);
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $route
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery route not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified delivery route
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'route_name' => 'sometimes|required|string|max:255',
            'route_date' => 'sometimes|required|date',
            'driver_id' => 'sometimes|required|exists:users,id',
            'vehicle_id' => 'nullable|string|max:100',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i|after:start_time',
            'total_distance' => 'nullable|numeric|min:0',
            'estimated_duration' => 'nullable|integer|min:0'
        ]);

        try {
            $route = DeliveryRoute::findOrFail($id);

            // Check if route can be updated
            if ($route->route_status === 'completed') {
                throw new \Exception('Cannot update completed routes');
            }

            $route->update($request->only([
                'route_name',
                'route_date',
                'driver_id',
                'vehicle_id',
                'start_time',
                'end_time',
                'total_distance',
                'estimated_duration'
            ]));

            $route->load([
                'driver:id,name,phone',
                'deliveryRouteStops.deliveryOrder.salesOrder.customer:id,name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery route updated successfully',
                'data' => $route
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery route',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Start a delivery route
     */
    public function start(Request $request, string $id): JsonResponse
    {
        try {
            $route = DeliveryRoute::findOrFail($id);

            if ($route->route_status !== 'planned') {
                throw new \Exception('Only planned routes can be started');
            }

            $route->update([
                'route_status' => 'in_progress',
                'start_time' => now()->format('H:i'),
                'actual_start_time' => now()
            ]);

            // Update all delivery orders in this route to in_transit
            foreach ($route->deliveryRouteStops as $stop) {
                $stop->deliveryOrder->update([
                    'delivery_status' => 'in_transit',
                    'driver_id' => $route->driver_id,
                    'vehicle_id' => $route->vehicle_id,
                    'shipped_at' => now()
                ]);
            }

            $route->load(['driver:id,name', 'deliveryRouteStops']);

            return response()->json([
                'success' => true,
                'message' => 'Delivery route started successfully',
                'data' => $route
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start delivery route',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Complete a delivery route
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'end_time' => 'nullable|date_format:H:i',
            'total_distance' => 'nullable|numeric|min:0',
            'route_notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            $route = DeliveryRoute::findOrFail($id);

            if ($route->route_status !== 'in_progress') {
                throw new \Exception('Only in-progress routes can be completed');
            }

            // Check if all stops are completed
            $incompleteStops = $route->deliveryRouteStops()
                ->where('stop_status', '!=', 'completed')
                ->count();

            if ($incompleteStops > 0) {
                throw new \Exception('All route stops must be completed before completing the route');
            }

            $route->update([
                'route_status' => 'completed',
                'end_time' => $request->end_time ?? now()->format('H:i'),
                'actual_end_time' => now(),
                'total_distance' => $request->total_distance ?? $route->total_distance,
                'route_notes' => $request->route_notes
            ]);

            DB::commit();

            $route->load(['driver:id,name', 'deliveryRouteStops']);

            return response()->json([
                'success' => true,
                'message' => 'Delivery route completed successfully',
                'data' => $route
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete delivery route',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Update route stop status
     */
    public function updateStop(Request $request, string $routeId, string $stopId): JsonResponse
    {
        $request->validate([
            'stop_status' => 'required|in:pending,arrived,completed,failed',
            'actual_arrival' => 'nullable|date_format:Y-m-d H:i:s',
            'stop_duration' => 'nullable|integer|min:0',
            'stop_notes' => 'nullable|string|max:500'
        ]);

        try {
            $route = DeliveryRoute::findOrFail($routeId);
            $stop = DeliveryRouteStop::where('delivery_route_id', $routeId)
                ->where('id', $stopId)
                ->firstOrFail();

            $stop->update([
                'stop_status' => $request->stop_status,
                'actual_arrival' => $request->actual_arrival ?? ($request->stop_status === 'arrived' ? now() : null),
                'stop_duration' => $request->stop_duration,
                'stop_notes' => $request->stop_notes
            ]);

            // Update delivery order status based on stop status
            if ($request->stop_status === 'completed') {
                $stop->deliveryOrder->update(['delivery_status' => 'delivered']);
            } elseif ($request->stop_status === 'failed') {
                $stop->deliveryOrder->update(['delivery_status' => 'failed']);
            }

            $stop->load(['deliveryOrder.salesOrder.customer:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Route stop updated successfully',
                'data' => $stop
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update route stop',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Optimize route stops order
     */
    public function optimize(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'optimization_type' => 'required|in:distance,time,priority',
            'stop_order' => 'sometimes|array',
            'stop_order.*' => 'exists:delivery_route_stops,id'
        ]);

        DB::beginTransaction();

        try {
            $route = DeliveryRoute::findOrFail($id);

            if ($route->route_status !== 'planned') {
                throw new \Exception('Only planned routes can be optimized');
            }

            if ($request->has('stop_order')) {
                // Manual reordering
                foreach ($request->stop_order as $sequence => $stopId) {
                    DeliveryRouteStop::where('id', $stopId)
                        ->where('delivery_route_id', $route->id)
                        ->update(['stop_sequence' => $sequence + 1]);
                }
            } else {
                // Automatic optimization (simplified algorithm)
                $stops = $route->deliveryRouteStops()
                    ->with('deliveryOrder.salesOrder.customer')
                    ->get();

                switch ($request->optimization_type) {
                    case 'priority':
                        // Sort by customer priority or order value
                        $stops = $stops->sortByDesc(function ($stop) {
                            return $stop->deliveryOrder->salesOrder->total_amount;
                        });
                        break;

                    case 'time':
                        // Sort by delivery time preference (simplified)
                        $stops = $stops->sortBy(function ($stop) {
                            return $stop->deliveryOrder->delivery_date;
                        });
                        break;

                    case 'distance':
                    default:
                        // Basic geographical optimization (would need coordinates)
                        // For now, sort alphabetically by area/city
                        $stops = $stops->sortBy(function ($stop) {
                            return $stop->deliveryOrder->delivery_address;
                        });
                        break;
                }

                // Update stop sequences
                $sequence = 1;
                foreach ($stops as $stop) {
                    $stop->update(['stop_sequence' => $sequence]);
                    $sequence++;
                }
            }

            DB::commit();

            $route->load([
                'deliveryRouteStops' => function ($query) {
                    $query->orderBy('stop_sequence')
                        ->with('deliveryOrder.salesOrder.customer:id,name');
                }
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Route optimized successfully',
                'data' => $route
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to optimize route',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get route statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth());

            $stats = [
                'total_routes' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])->count(),
                'routes_by_status' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])
                    ->selectRaw('route_status, COUNT(*) as count')
                    ->groupBy('route_status')
                    ->get(),
                'completed_routes' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])
                    ->where('route_status', 'completed')
                    ->count(),
                'total_deliveries' => DeliveryRouteStop::whereHas('deliveryRoute', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('route_date', [$dateFrom, $dateTo]);
                })->count(),
                'successful_deliveries' => DeliveryRouteStop::whereHas('deliveryRoute', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('route_date', [$dateFrom, $dateTo]);
                })->where('stop_status', 'completed')->count(),
                'failed_deliveries' => DeliveryRouteStop::whereHas('deliveryRoute', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('route_date', [$dateFrom, $dateTo]);
                })->where('stop_status', 'failed')->count(),
                'routes_by_driver' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])
                    ->with('driver:id,name')
                    ->selectRaw('driver_id, COUNT(*) as route_count')
                    ->groupBy('driver_id')
                    ->get(),
                'average_stops_per_route' => DeliveryRouteStop::whereHas('deliveryRoute', function ($query) use ($dateFrom, $dateTo) {
                    $query->whereBetween('route_date', [$dateFrom, $dateTo]);
                })->count() / max(DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])->count(), 1),
                'average_route_duration' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])
                    ->where('route_status', 'completed')
                    ->whereNotNull('actual_start_time')
                    ->whereNotNull('actual_end_time')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, actual_start_time, actual_end_time)) as avg_duration')
                    ->value('avg_duration'),
                'total_distance' => DeliveryRoute::whereBetween('route_date', [$dateFrom, $dateTo])
                    ->sum('total_distance')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'period' => [
                    'from' => $dateFrom,
                    'to' => $dateTo
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch route statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get unassigned delivery orders for route planning
     */
    public function unassignedDeliveryOrders(Request $request): JsonResponse
    {
        try {
            $query = DeliveryOrder::with([
                'salesOrder.customer:id,name,customer_code,phone',
                'warehouse:id,name,location'
            ])
                ->where('delivery_status', 'pending')
                ->whereDoesntHave('deliveryRouteStops', function ($stopQuery) {
                    $stopQuery->whereHas('deliveryRoute', function ($routeQuery) {
                        $routeQuery->where('route_status', '!=', 'completed');
                    });
                });

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('delivery_date')) {
                $query->whereDate('delivery_date', $request->delivery_date);
            }

            $deliveryOrders = $query->orderBy('delivery_date')->get();

            return response()->json([
                'success' => true,
                'data' => $deliveryOrders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unassigned delivery orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
