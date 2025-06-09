<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryRoute;
use App\Models\DeliveryRouteStop;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class DeliveryOrderController extends Controller
{
    /**
     * Display a listing of delivery orders with filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DeliveryOrder::with([
                'salesOrder.customer:id,name,customer_code,phone',
                'warehouse:id,name,location',
                'driver:id,name,phone',
                'deliveryOrderItems.product:id,name,sku,unit_name'
            ]);

            // Filtering
            if ($request->filled('sales_order_id')) {
                $query->where('sales_order_id', $request->sales_order_id);
            }

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('driver_id')) {
                $query->where('driver_id', $request->driver_id);
            }

            if ($request->filled('delivery_status')) {
                $statuses = is_array($request->delivery_status) ? $request->delivery_status : [$request->delivery_status];
                $query->whereIn('delivery_status', $statuses);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('delivery_order_number', 'like', "%{$search}%")
                        ->orWhere('delivery_address', 'like', "%{$search}%")
                        ->orWhere('delivery_contact', 'like', "%{$search}%")
                        ->orWhereHas('salesOrder.customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        });
                });
            }

            // Date filtering
            if ($request->filled('date_from')) {
                $query->whereDate('delivery_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('delivery_date', '<=', $request->date_to);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $deliveryOrders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $deliveryOrders,
                'filters_applied' => $request->only([
                    'sales_order_id',
                    'warehouse_id',
                    'driver_id',
                    'delivery_status',
                    'search',
                    'date_from',
                    'date_to'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch delivery orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created delivery order
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'sales_order_id' => 'required|exists:sales_orders,id',
            'delivery_date' => 'required|date|after_or_equal:today',
            'delivery_address' => 'required|string|max:500',
            'delivery_contact' => 'required|string|max:255',
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|string|max:100',
            'delivery_notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sales_order_item_id' => 'required|exists:sales_order_items,id',
            'items.*.quantity_to_deliver' => 'required|numeric|min:0.01',
            'items.*.delivery_notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $salesOrder = SalesOrder::findOrFail($request->sales_order_id);

            // Check if sales order is approved
            if ($salesOrder->order_status !== 'approved') {
                throw new \Exception('Sales order must be approved before creating delivery order');
            }

            // Validate delivery quantities
            foreach ($request->items as $item) {
                $salesOrderItem = SalesOrderItem::find($item['sales_order_item_id']);

                if (!$salesOrderItem || $salesOrderItem->sales_order_id != $request->sales_order_id) {
                    throw new \Exception('Invalid sales order item');
                }

                if ($item['quantity_to_deliver'] > $salesOrderItem->quantity_remaining) {
                    throw new \Exception(
                        "Cannot deliver {$item['quantity_to_deliver']} of {$salesOrderItem->product->name}. " .
                            "Only {$salesOrderItem->quantity_remaining} remaining."
                    );
                }
            }

            // Create delivery order
            $deliveryOrder = DeliveryOrder::create([
                'sales_order_id' => $request->sales_order_id,
                'warehouse_id' => $salesOrder->warehouse_id,
                'delivery_date' => $request->delivery_date,
                'delivery_address' => $request->delivery_address,
                'delivery_contact' => $request->delivery_contact,
                'driver_id' => $request->driver_id,
                'vehicle_id' => $request->vehicle_id,
                'delivery_notes' => $request->delivery_notes,
                'delivery_status' => 'pending'
            ]);

            // Create delivery order items
            foreach ($request->items as $item) {
                $salesOrderItem = SalesOrderItem::find($item['sales_order_item_id']);

                DeliveryOrderItem::create([
                    'delivery_order_id' => $deliveryOrder->id,
                    'sales_order_item_id' => $item['sales_order_item_id'],
                    'product_id' => $salesOrderItem->product_id,
                    'quantity_to_deliver' => $item['quantity_to_deliver'],
                    'quantity_delivered' => 0,
                    'unit_price' => $salesOrderItem->unit_price,
                    'line_total' => $item['quantity_to_deliver'] * $salesOrderItem->unit_price,
                    'delivery_notes' => $item['delivery_notes'] ?? null
                ]);
            }

            // Update sales order status to in_progress
            $salesOrder->update(['order_status' => 'in_progress']);

            DB::commit();

            // Load relationships for response
            $deliveryOrder->load([
                'salesOrder.customer:id,name,customer_code',
                'warehouse:id,name,location',
                'driver:id,name,phone',
                'deliveryOrderItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery order created successfully',
                'data' => $deliveryOrder
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create delivery order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified delivery order
     */
    public function show(string $id): JsonResponse
    {
        try {
            $deliveryOrder = DeliveryOrder::with([
                'salesOrder' => function ($query) {
                    $query->with([
                        'customer:id,name,customer_code,phone,email,address',
                        'salesRep:id,name,phone,email'
                    ]);
                },
                'warehouse:id,name,location,contact_person,phone',
                'driver:id,name,phone,email',
                'deliveryOrderItems' => function ($query) {
                    $query->with([
                        'product:id,name,sku,unit_name',
                        'salesOrderItem:id,quantity_ordered,quantity_delivered,quantity_remaining'
                    ]);
                },
                'deliveryRouteStops.deliveryRoute:id,route_name,driver_id'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $deliveryOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified delivery order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'delivery_date' => 'sometimes|required|date',
            'delivery_address' => 'sometimes|required|string|max:500',
            'delivery_contact' => 'sometimes|required|string|max:255',
            'driver_id' => 'nullable|exists:users,id',
            'vehicle_id' => 'nullable|string|max:100',
            'delivery_notes' => 'nullable|string|max:1000',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:delivery_order_items,id',
            'items.*.quantity_to_deliver' => 'required|numeric|min:0.01',
            'items.*.delivery_notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $deliveryOrder = DeliveryOrder::findOrFail($id);

            // Check if delivery order can be updated
            if (in_array($deliveryOrder->delivery_status, ['delivered', 'failed'])) {
                throw new \Exception('Cannot update completed delivery orders');
            }

            // Update basic delivery order information
            $deliveryOrder->update($request->only([
                'delivery_date',
                'delivery_address',
                'delivery_contact',
                'driver_id',
                'vehicle_id',
                'delivery_notes'
            ]));

            // Update items if provided
            if ($request->has('items')) {
                foreach ($request->items as $itemData) {
                    if (isset($itemData['id'])) {
                        $item = DeliveryOrderItem::find($itemData['id']);
                        if ($item && $item->delivery_order_id == $deliveryOrder->id) {
                            $item->update([
                                'quantity_to_deliver' => $itemData['quantity_to_deliver'],
                                'line_total' => $itemData['quantity_to_deliver'] * $item->unit_price,
                                'delivery_notes' => $itemData['delivery_notes'] ?? null
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            // Load relationships for response
            $deliveryOrder->load([
                'salesOrder.customer:id,name,customer_code',
                'warehouse:id,name,location',
                'driver:id,name,phone',
                'deliveryOrderItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery order updated successfully',
                'data' => $deliveryOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update delivery order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mark delivery order as shipped (in transit)
     */
    public function ship(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'driver_id' => 'required|exists:users,id',
            'vehicle_id' => 'nullable|string|max:100',
            'shipped_notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $deliveryOrder = DeliveryOrder::findOrFail($id);

            if ($deliveryOrder->delivery_status !== 'pending') {
                throw new \Exception('Only pending delivery orders can be shipped');
            }

            $deliveryOrder->update([
                'delivery_status' => 'in_transit',
                'driver_id' => $request->driver_id,
                'vehicle_id' => $request->vehicle_id,
                'shipped_at' => now(),
                'delivery_notes' => $deliveryOrder->delivery_notes . "\n" . ($request->shipped_notes ?? '')
            ]);

            // Update stock for shipped items (move from reserved to committed)
            foreach ($deliveryOrder->deliveryOrderItems as $item) {
                DB::table('warehouse_stock')
                    ->where('warehouse_id', $deliveryOrder->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->decrement('reserved_quantity', $item->quantity_to_deliver);
            }

            DB::commit();

            $deliveryOrder->load(['salesOrder.customer:id,name', 'driver:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Delivery order shipped successfully',
                'data' => $deliveryOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to ship delivery order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mark delivery order as delivered
     */
    public function deliver(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'delivered_items' => 'required|array|min:1',
            'delivered_items.*.delivery_order_item_id' => 'required|exists:delivery_order_items,id',
            'delivered_items.*.quantity_delivered' => 'required|numeric|min:0',
            'delivered_items.*.delivery_condition' => 'nullable|in:good,damaged,partial',
            'delivered_items.*.notes' => 'nullable|string|max:500',
            'delivery_confirmed_by' => 'required|string|max:255',
            'delivery_notes' => 'nullable|string|max:1000'
        ]);

        DB::beginTransaction();

        try {
            $deliveryOrder = DeliveryOrder::findOrFail($id);

            if ($deliveryOrder->delivery_status !== 'in_transit') {
                throw new \Exception('Only in-transit delivery orders can be marked as delivered');
            }

            $totalDelivered = 0;
            $totalToDeliver = 0;

            // Process delivered items
            foreach ($request->delivered_items as $deliveredItem) {
                $item = DeliveryOrderItem::find($deliveredItem['delivery_order_item_id']);

                if (!$item || $item->delivery_order_id != $deliveryOrder->id) {
                    throw new \Exception('Invalid delivery order item');
                }

                $quantityDelivered = $deliveredItem['quantity_delivered'];

                if ($quantityDelivered > $item->quantity_to_deliver) {
                    throw new \Exception(
                        "Cannot deliver {$quantityDelivered} of {$item->product->name}. " .
                            "Only {$item->quantity_to_deliver} was scheduled for delivery."
                    );
                }

                // Update delivery order item
                $item->update([
                    'quantity_delivered' => $quantityDelivered,
                    'delivery_condition' => $deliveredItem['delivery_condition'] ?? 'good',
                    'delivery_notes' => $deliveredItem['notes'] ?? null
                ]);

                // Update sales order item
                $salesOrderItem = $item->salesOrderItem;
                $salesOrderItem->increment('quantity_delivered', $quantityDelivered);
                $salesOrderItem->decrement('quantity_remaining', $quantityDelivered);

                // Update delivery status of sales order item
                if ($salesOrderItem->quantity_remaining == 0) {
                    $salesOrderItem->update(['delivery_status' => 'completed']);
                } elseif ($salesOrderItem->quantity_delivered > 0) {
                    $salesOrderItem->update(['delivery_status' => 'partial']);
                }

                $totalDelivered += $quantityDelivered;
                $totalToDeliver += $item->quantity_to_deliver;
            }

            // Update delivery order status
            $deliveryStatus = ($totalDelivered == $totalToDeliver) ? 'delivered' : 'partially_delivered';

            $deliveryOrder->update([
                'delivery_status' => $deliveryStatus,
                'delivered_at' => now(),
                'delivery_confirmed_by' => $request->delivery_confirmed_by,
                'delivery_notes' => $deliveryOrder->delivery_notes . "\n" . ($request->delivery_notes ?? '')
            ]);

            // Check if sales order is completed
            $salesOrder = $deliveryOrder->salesOrder;
            $allItemsCompleted = $salesOrder->salesOrderItems()
                ->where('delivery_status', '!=', 'completed')
                ->count() == 0;

            if ($allItemsCompleted) {
                $salesOrder->update(['order_status' => 'completed']);
            }

            DB::commit();

            $deliveryOrder->load([
                'salesOrder.customer:id,name',
                'deliveryOrderItems.product:id,name,sku'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Delivery completed successfully',
                'data' => $deliveryOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete delivery',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Mark delivery as failed
     */
    public function fail(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'failure_reason' => 'required|string|max:500',
            'reschedule_date' => 'nullable|date|after:today'
        ]);

        DB::beginTransaction();

        try {
            $deliveryOrder = DeliveryOrder::findOrFail($id);

            if (!in_array($deliveryOrder->delivery_status, ['pending', 'in_transit'])) {
                throw new \Exception('Cannot mark completed deliveries as failed');
            }

            $deliveryOrder->update([
                'delivery_status' => 'failed',
                'delivery_notes' => $deliveryOrder->delivery_notes . "\nFailure: " . $request->failure_reason
            ]);

            // If reschedule date is provided, create a new delivery order
            if ($request->filled('reschedule_date')) {
                $newDeliveryOrder = $deliveryOrder->replicate();
                $newDeliveryOrder->delivery_date = $request->reschedule_date;
                $newDeliveryOrder->delivery_status = 'pending';
                $newDeliveryOrder->shipped_at = null;
                $newDeliveryOrder->delivered_at = null;
                $newDeliveryOrder->delivery_confirmed_by = null;
                $newDeliveryOrder->save();

                // Copy delivery order items
                foreach ($deliveryOrder->deliveryOrderItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->delivery_order_id = $newDeliveryOrder->id;
                    $newItem->quantity_delivered = 0;
                    $newItem->delivery_condition = null;
                    $newItem->save();
                }
            }

            // Return reserved stock if delivery failed
            if ($deliveryOrder->delivery_status === 'in_transit') {
                foreach ($deliveryOrder->deliveryOrderItems as $item) {
                    DB::table('warehouse_stock')
                        ->where('warehouse_id', $deliveryOrder->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->increment('reserved_quantity', $item->quantity_to_deliver);
                }
            }

            DB::commit();

            $response = [
                'success' => true,
                'message' => 'Delivery marked as failed',
                'data' => $deliveryOrder
            ];

            if (isset($newDeliveryOrder)) {
                $response['message'] .= ' and rescheduled';
                $response['rescheduled_delivery'] = $newDeliveryOrder;
            }

            return response()->json($response);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark delivery as failed',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get delivery order statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth());

            $stats = [
                'total_deliveries' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])->count(),
                'deliveries_by_status' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->selectRaw('delivery_status, COUNT(*) as count')
                    ->groupBy('delivery_status')
                    ->get(),
                'on_time_deliveries' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->where('delivery_status', 'delivered')
                    ->whereRaw('DATE(delivered_at) <= delivery_date')
                    ->count(),
                'late_deliveries' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->where('delivery_status', 'delivered')
                    ->whereRaw('DATE(delivered_at) > delivery_date')
                    ->count(),
                'failed_deliveries' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->where('delivery_status', 'failed')
                    ->count(),
                'deliveries_by_driver' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->with('driver:id,name')
                    ->selectRaw('driver_id, COUNT(*) as count')
                    ->whereNotNull('driver_id')
                    ->groupBy('driver_id')
                    ->get(),
                'average_delivery_time' => DeliveryOrder::whereBetween('delivery_date', [$dateFrom, $dateTo])
                    ->where('delivery_status', 'delivered')
                    ->whereNotNull('shipped_at')
                    ->whereNotNull('delivered_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, shipped_at, delivered_at)) as avg_hours')
                    ->value('avg_hours'),
                'pending_deliveries' => DeliveryOrder::where('delivery_status', 'pending')
                    ->where('delivery_date', '<=', now()->addDays(3))
                    ->count(),
                'overdue_deliveries' => DeliveryOrder::where('delivery_status', 'pending')
                    ->where('delivery_date', '<', now())
                    ->count()
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
                'message' => 'Failed to fetch delivery statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get drivers list for delivery assignment
     */
    public function drivers(Request $request): JsonResponse
    {
        try {
            $drivers = User::select('id', 'name', 'phone', 'email')
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['delivery-driver', 'warehouse-manager', 'manager', 'super-admin']);
                })
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $drivers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch drivers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales orders available for delivery
     */
    public function availableSalesOrders(Request $request): JsonResponse
    {
        try {
            $query = SalesOrder::with([
                'customer:id,name,customer_code,address',
                'warehouse:id,name,location',
                'salesOrderItems' => function ($q) {
                    $q->with('product:id,name,sku,unit_name')
                        ->where('quantity_remaining', '>', 0);
                }
            ])
                ->where('order_status', 'approved')
                ->whereHas('salesOrderItems', function ($q) {
                    $q->where('quantity_remaining', '>', 0);
                });

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            $salesOrders = $query->orderBy('confirmed_delivery_date')->get();

            return response()->json([
                'success' => true,
                'data' => $salesOrders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available sales orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
