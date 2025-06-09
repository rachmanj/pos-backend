<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class SalesOrderController extends Controller
{
    /**
     * Display a listing of sales orders with comprehensive filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesOrder::with([
                'customer:id,name,customer_code,phone,email',
                'warehouse:id,name,location',
                'salesRep:id,name,email',
                'createdBy:id,name',
                'approvedBy:id,name',
                'salesOrderItems.product:id,name,sku,unit_name'
            ]);

            // Advanced filtering
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->filled('sales_rep_id')) {
                $query->where('sales_rep_id', $request->sales_rep_id);
            }

            if ($request->filled('order_status')) {
                $statuses = is_array($request->order_status) ? $request->order_status : [$request->order_status];
                $query->whereIn('order_status', $statuses);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('sales_order_number', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('special_instructions', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        });
                });
            }

            // Date range filtering
            if ($request->filled('date_from')) {
                $query->whereDate('order_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('order_date', '<=', $request->date_to);
            }

            if ($request->filled('delivery_date_from')) {
                $query->whereDate('requested_delivery_date', '>=', $request->delivery_date_from);
            }

            if ($request->filled('delivery_date_to')) {
                $query->whereDate('requested_delivery_date', '<=', $request->delivery_date_to);
            }

            // Amount range filtering
            if ($request->filled('min_amount')) {
                $query->where('total_amount', '>=', $request->min_amount);
            }

            if ($request->filled('max_amount')) {
                $query->where('total_amount', '<=', $request->max_amount);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $salesOrders = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $salesOrders,
                'filters_applied' => $request->only([
                    'customer_id',
                    'warehouse_id',
                    'sales_rep_id',
                    'order_status',
                    'search',
                    'date_from',
                    'date_to',
                    'delivery_date_from',
                    'delivery_date_to',
                    'min_amount',
                    'max_amount'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created sales order
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'order_date' => 'required|date',
            'requested_delivery_date' => 'required|date|after_or_equal:order_date',
            'payment_terms_days' => 'required|integer|min:0|max:365',
            'sales_rep_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'special_instructions' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            // Validate stock availability for all items
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product) {
                    throw new \Exception("Product not found: {$item['product_id']}");
                }

                if (!$product->is_orderable) {
                    throw new \Exception("Product {$product->name} is not available for ordering");
                }

                // Check stock availability in the specified warehouse
                $warehouseStock = DB::table('warehouse_stock')
                    ->where('warehouse_id', $request->warehouse_id)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $availableStock = $warehouseStock ? $warehouseStock->quantity : 0;

                if ($availableStock < $item['quantity_ordered']) {
                    throw new \Exception(
                        "Insufficient stock for {$product->name}. Available: {$availableStock}, Requested: {$item['quantity_ordered']}"
                    );
                }
            }

            // Create sales order
            $salesOrder = SalesOrder::create([
                'customer_id' => $request->customer_id,
                'warehouse_id' => $request->warehouse_id,
                'order_date' => $request->order_date,
                'requested_delivery_date' => $request->requested_delivery_date,
                'payment_terms_days' => $request->payment_terms_days,
                'sales_rep_id' => $request->sales_rep_id,
                'notes' => $request->notes,
                'special_instructions' => $request->special_instructions,
                'order_status' => 'draft',
                'created_by' => Auth::id(),
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0
            ]);

            // Create sales order items and calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($request->items as $item) {
                $lineTotal = $item['quantity_ordered'] * $item['unit_price'];
                $discountAmount = $item['discount_amount'] ?? 0;
                $taxRate = $item['tax_rate'] ?? 0;

                $lineSubtotal = $lineTotal - $discountAmount;
                $lineTax = ($lineSubtotal * $taxRate) / 100;
                $lineTotalWithTax = $lineSubtotal + $lineTax;

                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => $item['quantity_ordered'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotalWithTax,
                    'quantity_delivered' => 0,
                    'quantity_remaining' => $item['quantity_ordered'],
                    'delivery_status' => 'pending',
                    'notes' => $item['notes'] ?? null
                ]);

                $subtotal += $lineTotal;
                $totalDiscount += $discountAmount;
                $totalTax += $lineTax;
            }

            // Update sales order totals
            $salesOrder->update([
                'subtotal_amount' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal - $totalDiscount + $totalTax
            ]);

            DB::commit();

            // Load relationships for response
            $salesOrder->load([
                'customer:id,name,customer_code',
                'warehouse:id,name,location',
                'salesRep:id,name',
                'createdBy:id,name',
                'salesOrderItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales order created successfully',
                'data' => $salesOrder
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified sales order
     */
    public function show(string $id): JsonResponse
    {
        try {
            $salesOrder = SalesOrder::with([
                'customer:id,name,customer_code,phone,email,address,city,state,postal_code',
                'warehouse:id,name,location,contact_person,phone,email',
                'salesRep:id,name,email,phone',
                'createdBy:id,name,email',
                'approvedBy:id,name,email',
                'cancelledBy:id,name,email',
                'salesOrderItems' => function ($query) {
                    $query->with('product:id,name,sku,unit_name,description');
                },
                'deliveryOrders' => function ($query) {
                    $query->with(['deliveryOrderItems.product:id,name,sku']);
                },
                'salesInvoices' => function ($query) {
                    $query->with('salesInvoiceItems.product:id,name,sku');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sales order not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified sales order
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'warehouse_id' => 'sometimes|required|exists:warehouses,id',
            'order_date' => 'sometimes|required|date',
            'requested_delivery_date' => 'sometimes|required|date',
            'payment_terms_days' => 'sometimes|required|integer|min:0|max:365',
            'sales_rep_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:1000',
            'special_instructions' => 'nullable|string|max:1000',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:sales_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.notes' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $salesOrder = SalesOrder::findOrFail($id);

            // Check if order can be updated
            if (in_array($salesOrder->order_status, ['completed', 'cancelled'])) {
                throw new \Exception('Cannot update completed or cancelled orders');
            }

            if (in_array($salesOrder->order_status, ['approved', 'in_progress']) && !Auth::user()->hasRole(['super-admin', 'manager'])) {
                throw new \Exception('Insufficient permissions to update approved orders');
            }

            // Update sales order basic information
            $salesOrder->update($request->only([
                'customer_id',
                'warehouse_id',
                'order_date',
                'requested_delivery_date',
                'payment_terms_days',
                'sales_rep_id',
                'notes',
                'special_instructions'
            ]));

            // Update items if provided
            if ($request->has('items')) {
                // Remove existing items that are not in the update
                $updatedItemIds = collect($request->items)->pluck('id')->filter();
                $salesOrder->salesOrderItems()->whereNotIn('id', $updatedItemIds)->delete();

                $subtotal = 0;
                $totalTax = 0;
                $totalDiscount = 0;

                foreach ($request->items as $itemData) {
                    $lineTotal = $itemData['quantity_ordered'] * $itemData['unit_price'];
                    $discountAmount = $itemData['discount_amount'] ?? 0;
                    $taxRate = $itemData['tax_rate'] ?? 0;

                    $lineSubtotal = $lineTotal - $discountAmount;
                    $lineTax = ($lineSubtotal * $taxRate) / 100;
                    $lineTotalWithTax = $lineSubtotal + $lineTax;

                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = SalesOrderItem::find($itemData['id']);
                        if ($item) {
                            $item->update([
                                'product_id' => $itemData['product_id'],
                                'quantity_ordered' => $itemData['quantity_ordered'],
                                'unit_price' => $itemData['unit_price'],
                                'discount_amount' => $discountAmount,
                                'tax_rate' => $taxRate,
                                'line_total' => $lineTotalWithTax,
                                'quantity_remaining' => $itemData['quantity_ordered'] - $item->quantity_delivered,
                                'notes' => $itemData['notes'] ?? null
                            ]);
                        }
                    } else {
                        // Create new item
                        SalesOrderItem::create([
                            'sales_order_id' => $salesOrder->id,
                            'product_id' => $itemData['product_id'],
                            'quantity_ordered' => $itemData['quantity_ordered'],
                            'unit_price' => $itemData['unit_price'],
                            'discount_amount' => $discountAmount,
                            'tax_rate' => $taxRate,
                            'line_total' => $lineTotalWithTax,
                            'quantity_delivered' => 0,
                            'quantity_remaining' => $itemData['quantity_ordered'],
                            'delivery_status' => 'pending',
                            'notes' => $itemData['notes'] ?? null
                        ]);
                    }

                    $subtotal += $lineTotal;
                    $totalDiscount += $discountAmount;
                    $totalTax += $lineTax;
                }

                // Update totals
                $salesOrder->update([
                    'subtotal_amount' => $subtotal,
                    'discount_amount' => $totalDiscount,
                    'tax_amount' => $totalTax,
                    'total_amount' => $subtotal - $totalDiscount + $totalTax
                ]);
            }

            DB::commit();

            // Load relationships for response
            $salesOrder->load([
                'customer:id,name,customer_code',
                'warehouse:id,name,location',
                'salesRep:id,name',
                'salesOrderItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales order updated successfully',
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified sales order
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $salesOrder = SalesOrder::findOrFail($id);

            // Check if order can be deleted
            if (in_array($salesOrder->order_status, ['approved', 'in_progress', 'completed'])) {
                throw new \Exception('Cannot delete approved, in-progress, or completed orders');
            }

            if ($salesOrder->deliveryOrders()->exists()) {
                throw new \Exception('Cannot delete orders with delivery orders');
            }

            if ($salesOrder->salesInvoices()->exists()) {
                throw new \Exception('Cannot delete orders with invoices');
            }

            DB::beginTransaction();

            // Delete order items first
            $salesOrder->salesOrderItems()->delete();

            // Delete the order
            $salesOrder->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sales order deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Confirm a sales order (move from draft to confirmed)
     */
    public function confirm(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $salesOrder = SalesOrder::findOrFail($id);

            if ($salesOrder->order_status !== 'draft') {
                throw new \Exception('Only draft orders can be confirmed');
            }

            // Check stock availability again before confirmation
            foreach ($salesOrder->salesOrderItems as $item) {
                $warehouseStock = DB::table('warehouse_stock')
                    ->where('warehouse_id', $salesOrder->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                $availableStock = $warehouseStock ? $warehouseStock->quantity : 0;

                if ($availableStock < $item->quantity_ordered) {
                    throw new \Exception(
                        "Insufficient stock for {$item->product->name}. Available: {$availableStock}, Required: {$item->quantity_ordered}"
                    );
                }
            }

            $salesOrder->update([
                'order_status' => 'confirmed',
                'confirmed_delivery_date' => $request->confirmed_delivery_date ?? $salesOrder->requested_delivery_date
            ]);

            // Reserve inventory for confirmed orders
            foreach ($salesOrder->salesOrderItems as $item) {
                DB::table('warehouse_stock')
                    ->where('warehouse_id', $salesOrder->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->decrement('quantity', $item->quantity_ordered);

                DB::table('warehouse_stock')
                    ->where('warehouse_id', $salesOrder->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->increment('reserved_quantity', $item->quantity_ordered);
            }

            DB::commit();

            $salesOrder->load(['customer:id,name', 'warehouse:id,name', 'salesOrderItems.product:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Sales order confirmed successfully',
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Approve a sales order (requires approval for credit orders)
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $salesOrder = SalesOrder::findOrFail($id);

            if (!in_array($salesOrder->order_status, ['confirmed'])) {
                throw new \Exception('Only confirmed orders can be approved');
            }

            // Check user permissions for approval
            if (!Auth::user()->hasAnyRole(['super-admin', 'manager', 'sales-manager'])) {
                throw new \Exception('Insufficient permissions to approve orders');
            }

            // Credit check for customers with payment terms
            if ($salesOrder->payment_terms_days > 0) {
                $customer = $salesOrder->customer;

                // Get customer's current AR balance
                $currentBalance = DB::table('customer_payment_receives')
                    ->join('customer_payment_allocations', 'customer_payment_receives.id', '=', 'customer_payment_allocations.customer_payment_receive_id')
                    ->join('sales', 'customer_payment_allocations.sale_id', '=', 'sales.id')
                    ->where('sales.customer_id', $customer->id)
                    ->where('sales.payment_status', '!=', 'paid')
                    ->sum('sales.total_amount');

                $totalExposure = $currentBalance + $salesOrder->total_amount;

                if ($customer->credit_limit > 0 && $totalExposure > $customer->credit_limit) {
                    throw new \Exception(
                        "Credit limit exceeded. Current balance: " . number_format($currentBalance, 2) .
                            ", Order amount: " . number_format($salesOrder->total_amount, 2) .
                            ", Credit limit: " . number_format($customer->credit_limit, 2)
                    );
                }
            }

            $salesOrder->update([
                'order_status' => 'approved',
                'credit_approved_by' => Auth::id(),
                'credit_approval_date' => now(),
                'approved_by' => Auth::id()
            ]);

            DB::commit();

            $salesOrder->load(['customer:id,name', 'approvedBy:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Sales order approved successfully',
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to approve sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Cancel a sales order
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'cancellation_reason' => 'required|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $salesOrder = SalesOrder::findOrFail($id);

            if (in_array($salesOrder->order_status, ['completed', 'cancelled'])) {
                throw new \Exception('Cannot cancel completed or already cancelled orders');
            }

            if ($salesOrder->order_status === 'in_progress') {
                throw new \Exception('Cannot cancel orders that are in progress');
            }

            // Release reserved inventory if order was confirmed or approved
            if (in_array($salesOrder->order_status, ['confirmed', 'approved'])) {
                foreach ($salesOrder->salesOrderItems as $item) {
                    DB::table('warehouse_stock')
                        ->where('warehouse_id', $salesOrder->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->increment('quantity', $item->quantity_ordered);

                    DB::table('warehouse_stock')
                        ->where('warehouse_id', $salesOrder->warehouse_id)
                        ->where('product_id', $item->product_id)
                        ->decrement('reserved_quantity', $item->quantity_ordered);
                }
            }

            $salesOrder->update([
                'order_status' => 'cancelled',
                'cancelled_by' => Auth::id(),
                'cancellation_reason' => $request->cancellation_reason
            ]);

            DB::commit();

            $salesOrder->load(['customer:id,name', 'cancelledBy:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Sales order cancelled successfully',
                'data' => $salesOrder
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel sales order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get sales order statistics and analytics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth());

            $stats = [
                'total_orders' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])->count(),
                'total_value' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])->sum('total_amount'),
                'orders_by_status' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])
                    ->selectRaw('order_status, COUNT(*) as count, SUM(total_amount) as total_value')
                    ->groupBy('order_status')
                    ->get(),
                'orders_by_warehouse' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])
                    ->with('warehouse:id,name')
                    ->selectRaw('warehouse_id, COUNT(*) as count, SUM(total_amount) as total_value')
                    ->groupBy('warehouse_id')
                    ->get(),
                'top_customers' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])
                    ->with('customer:id,name,customer_code')
                    ->selectRaw('customer_id, COUNT(*) as order_count, SUM(total_amount) as total_value')
                    ->groupBy('customer_id')
                    ->orderByDesc('total_value')
                    ->limit(10)
                    ->get(),
                'average_order_value' => SalesOrder::whereBetween('order_date', [$dateFrom, $dateTo])->avg('total_amount'),
                'pending_approval' => SalesOrder::where('order_status', 'confirmed')->count(),
                'overdue_deliveries' => SalesOrder::where('order_status', 'approved')
                    ->where('confirmed_delivery_date', '<', now())
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
                'message' => 'Failed to fetch sales order statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customers list for sales order creation
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            $query = Customer::select('id', 'name', 'customer_code', 'phone', 'email', 'credit_limit', 'payment_terms_days')
                ->where('status', 'active');

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $customers = $query->orderBy('name')->get();

            return response()->json([
                'success' => true,
                'data' => $customers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products list for sales order creation
     */
    public function products(Request $request): JsonResponse
    {
        try {
            $warehouseId = $request->get('warehouse_id');

            $query = Product::select('id', 'name', 'sku', 'unit_name', 'selling_price', 'wholesale_price', 'is_orderable', 'minimum_order_quantity')
                ->where('status', 'active')
                ->where('is_orderable', true);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                });
            }

            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            $products = $query->orderBy('name')->get();

            // Add stock information if warehouse is specified
            if ($warehouseId) {
                $products = $products->map(function ($product) use ($warehouseId) {
                    $stock = DB::table('warehouse_stock')
                        ->where('warehouse_id', $warehouseId)
                        ->where('product_id', $product->id)
                        ->first();

                    $product->available_stock = $stock ? $stock->quantity : 0;
                    $product->reserved_stock = $stock ? $stock->reserved_quantity : 0;

                    return $product;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $products
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get warehouses list for sales order creation
     */
    public function warehouses(Request $request): JsonResponse
    {
        try {
            $warehouses = Warehouse::select('id', 'name', 'location', 'contact_person', 'phone')
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch warehouses',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sales representatives list
     */
    public function salesReps(Request $request): JsonResponse
    {
        try {
            $salesReps = User::select('id', 'name', 'email', 'phone')
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', ['sales-manager', 'sales-rep', 'manager', 'super-admin']);
                })
                ->where('status', 'active')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $salesReps
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales representatives',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
