<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\DeliveryOrder;
use App\Models\SalesOrder;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SalesInvoiceController extends Controller
{
    /**
     * Display a listing of sales invoices with filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SalesInvoice::with([
                'customer:id,name,customer_code,phone,email',
                'salesOrder:id,sales_order_number,order_date',
                'deliveryOrder:id,delivery_order_number,delivery_date',
                'salesInvoiceItems.product:id,name,sku,unit_name'
            ]);

            // Filtering
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }

            if ($request->filled('sales_order_id')) {
                $query->where('sales_order_id', $request->sales_order_id);
            }

            if ($request->filled('invoice_status')) {
                $statuses = is_array($request->invoice_status) ? $request->invoice_status : [$request->invoice_status];
                $query->whereIn('invoice_status', $statuses);
            }

            if ($request->filled('payment_status')) {
                $statuses = is_array($request->payment_status) ? $request->payment_status : [$request->payment_status];
                $query->whereIn('payment_status', $statuses);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('customer_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('salesOrder', function ($orderQuery) use ($search) {
                            $orderQuery->where('sales_order_number', 'like', "%{$search}%");
                        });
                });
            }

            // Date filtering
            if ($request->filled('date_from')) {
                $query->whereDate('invoice_date', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('invoice_date', '<=', $request->date_to);
            }

            if ($request->filled('due_date_from')) {
                $query->whereDate('due_date', '>=', $request->due_date_from);
            }

            if ($request->filled('due_date_to')) {
                $query->whereDate('due_date', '<=', $request->due_date_to);
            }

            // Amount filtering
            if ($request->filled('min_amount')) {
                $query->where('total_amount', '>=', $request->min_amount);
            }

            if ($request->filled('max_amount')) {
                $query->where('total_amount', '<=', $request->max_amount);
            }

            // Overdue filter
            if ($request->filled('overdue') && $request->overdue) {
                $query->where('due_date', '<', now())
                    ->where('payment_status', '!=', 'paid');
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            // Pagination
            $perPage = min($request->get('per_page', 15), 100);
            $invoices = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $invoices,
                'filters_applied' => $request->only([
                    'customer_id',
                    'sales_order_id',
                    'invoice_status',
                    'payment_status',
                    'search',
                    'date_from',
                    'date_to',
                    'due_date_from',
                    'due_date_to',
                    'min_amount',
                    'max_amount',
                    'overdue'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sales invoices',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created sales invoice
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sales_order_id' => 'nullable|exists:sales_orders,id',
            'delivery_order_id' => 'nullable|exists:delivery_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'payment_terms_days' => 'required|integer|min:0|max:365',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            // Validate delivery order if specified
            if ($request->filled('delivery_order_id')) {
                $deliveryOrder = DeliveryOrder::find($request->delivery_order_id);

                if (!$deliveryOrder || $deliveryOrder->delivery_status !== 'delivered') {
                    throw new \Exception('Delivery order must be completed before invoicing');
                }

                // Check if already invoiced
                $existingInvoice = SalesInvoice::where('delivery_order_id', $request->delivery_order_id)->first();
                if ($existingInvoice) {
                    throw new \Exception('Delivery order has already been invoiced');
                }
            }

            // Create sales invoice
            $invoice = SalesInvoice::create([
                'customer_id' => $request->customer_id,
                'sales_order_id' => $request->sales_order_id,
                'delivery_order_id' => $request->delivery_order_id,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'payment_terms_days' => $request->payment_terms_days,
                'invoice_status' => 'draft',
                'payment_status' => 'unpaid',
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0
            ]);

            // Create invoice items and calculate totals
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            foreach ($request->items as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $discountAmount = $item['discount_amount'] ?? 0;
                $taxRate = $item['tax_rate'] ?? 11; // Default 11% PPN

                $lineSubtotal = $lineTotal - $discountAmount;
                $lineTax = ($lineSubtotal * $taxRate) / 100;
                $lineTotalWithTax = $lineSubtotal + $lineTax;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotalWithTax,
                    'description' => $item['description'] ?? null
                ]);

                $subtotal += $lineTotal;
                $totalDiscount += $discountAmount;
                $totalTax += $lineTax;
            }

            // Update invoice totals
            $invoice->update([
                'subtotal_amount' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal - $totalDiscount + $totalTax
            ]);

            DB::commit();

            // Load relationships for response
            $invoice->load([
                'customer:id,name,customer_code',
                'salesOrder:id,sales_order_number',
                'deliveryOrder:id,delivery_order_number',
                'salesInvoiceItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales invoice created successfully',
                'data' => $invoice
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create sales invoice',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified sales invoice
     */
    public function show(string $id): JsonResponse
    {
        try {
            $invoice = SalesInvoice::with([
                'customer:id,name,customer_code,phone,email,address,city,state,postal_code',
                'salesOrder' => function ($query) {
                    $query->with(['salesRep:id,name,phone,email']);
                },
                'deliveryOrder:id,delivery_order_number,delivery_date,delivery_address,delivery_contact',
                'salesInvoiceItems' => function ($query) {
                    $query->with('product:id,name,sku,unit_name,description');
                }
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Sales invoice not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified sales invoice
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'invoice_date' => 'sometimes|required|date',
            'due_date' => 'sometimes|required|date',
            'payment_terms_days' => 'sometimes|required|integer|min:0|max:365',
            'items' => 'sometimes|array|min:1',
            'items.*.id' => 'nullable|exists:sales_invoice_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.description' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $invoice = SalesInvoice::findOrFail($id);

            // Check if invoice can be updated
            if (in_array($invoice->invoice_status, ['sent', 'paid'])) {
                throw new \Exception('Cannot update sent or paid invoices');
            }

            if ($invoice->payment_status === 'paid') {
                throw new \Exception('Cannot update paid invoices');
            }

            // Update basic invoice information
            $invoice->update($request->only([
                'customer_id',
                'invoice_date',
                'due_date',
                'payment_terms_days'
            ]));

            // Update items if provided
            if ($request->has('items')) {
                // Remove existing items that are not in the update
                $updatedItemIds = collect($request->items)->pluck('id')->filter();
                $invoice->salesInvoiceItems()->whereNotIn('id', $updatedItemIds)->delete();

                $subtotal = 0;
                $totalTax = 0;
                $totalDiscount = 0;

                foreach ($request->items as $itemData) {
                    $lineTotal = $itemData['quantity'] * $itemData['unit_price'];
                    $discountAmount = $itemData['discount_amount'] ?? 0;
                    $taxRate = $itemData['tax_rate'] ?? 11;

                    $lineSubtotal = $lineTotal - $discountAmount;
                    $lineTax = ($lineSubtotal * $taxRate) / 100;
                    $lineTotalWithTax = $lineSubtotal + $lineTax;

                    if (isset($itemData['id'])) {
                        // Update existing item
                        $item = SalesInvoiceItem::find($itemData['id']);
                        if ($item) {
                            $item->update([
                                'product_id' => $itemData['product_id'],
                                'quantity' => $itemData['quantity'],
                                'unit_price' => $itemData['unit_price'],
                                'discount_amount' => $discountAmount,
                                'tax_rate' => $taxRate,
                                'line_total' => $lineTotalWithTax,
                                'description' => $itemData['description'] ?? null
                            ]);
                        }
                    } else {
                        // Create new item
                        SalesInvoiceItem::create([
                            'sales_invoice_id' => $invoice->id,
                            'product_id' => $itemData['product_id'],
                            'quantity' => $itemData['quantity'],
                            'unit_price' => $itemData['unit_price'],
                            'discount_amount' => $discountAmount,
                            'tax_rate' => $taxRate,
                            'line_total' => $lineTotalWithTax,
                            'description' => $itemData['description'] ?? null
                        ]);
                    }

                    $subtotal += $lineTotal;
                    $totalDiscount += $discountAmount;
                    $totalTax += $lineTax;
                }

                // Update totals
                $invoice->update([
                    'subtotal_amount' => $subtotal,
                    'discount_amount' => $totalDiscount,
                    'tax_amount' => $totalTax,
                    'total_amount' => $subtotal - $totalDiscount + $totalTax
                ]);
            }

            DB::commit();

            // Load relationships for response
            $invoice->load([
                'customer:id,name,customer_code',
                'salesInvoiceItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sales invoice updated successfully',
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update sales invoice',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified sales invoice
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $invoice = SalesInvoice::findOrFail($id);

            // Check if invoice can be deleted
            if (in_array($invoice->invoice_status, ['sent', 'paid'])) {
                throw new \Exception('Cannot delete sent or paid invoices');
            }

            if ($invoice->payment_status !== 'unpaid') {
                throw new \Exception('Cannot delete invoices with payments');
            }

            DB::beginTransaction();

            // Delete invoice items first
            $invoice->salesInvoiceItems()->delete();

            // Delete the invoice
            $invoice->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sales invoice deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete sales invoice',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Send invoice to customer (mark as sent)
     */
    public function send(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'send_method' => 'required|in:email,print,postal',
            'recipient_email' => 'required_if:send_method,email|email',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $invoice = SalesInvoice::findOrFail($id);

            if ($invoice->invoice_status !== 'draft') {
                throw new \Exception('Only draft invoices can be sent');
            }

            $invoice->update([
                'invoice_status' => 'sent',
                'sent_at' => now(),
                'sent_method' => $request->send_method,
                'sent_to' => $request->recipient_email ?? $invoice->customer->email,
                'sent_notes' => $request->notes
            ]);

            $invoice->load(['customer:id,name,email']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully',
                'data' => $invoice
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send invoice',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Generate invoice from delivery order
     */
    public function generateFromDelivery(Request $request): JsonResponse
    {
        $request->validate([
            'delivery_order_id' => 'required|exists:delivery_orders,id',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date'
        ]);

        DB::beginTransaction();

        try {
            $deliveryOrder = DeliveryOrder::with([
                'salesOrder.customer',
                'deliveryOrderItems.product'
            ])->findOrFail($request->delivery_order_id);

            if ($deliveryOrder->delivery_status !== 'delivered') {
                throw new \Exception('Delivery order must be completed before generating invoice');
            }

            // Check if already invoiced
            $existingInvoice = SalesInvoice::where('delivery_order_id', $deliveryOrder->id)->first();
            if ($existingInvoice) {
                throw new \Exception('Invoice already exists for this delivery order');
            }

            $customer = $deliveryOrder->salesOrder->customer;

            // Create invoice
            $invoice = SalesInvoice::create([
                'customer_id' => $customer->id,
                'sales_order_id' => $deliveryOrder->sales_order_id,
                'delivery_order_id' => $deliveryOrder->id,
                'invoice_date' => $request->invoice_date,
                'due_date' => $request->due_date,
                'payment_terms_days' => $customer->payment_terms_days ?? 30,
                'invoice_status' => 'draft',
                'payment_status' => 'unpaid',
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0
            ]);

            // Create invoice items from delivery order items
            $subtotal = 0;
            $totalTax = 0;

            foreach ($deliveryOrder->deliveryOrderItems as $deliveryItem) {
                $lineTotal = $deliveryItem->quantity_delivered * $deliveryItem->unit_price;
                $taxRate = 11; // Default PPN
                $lineTax = ($lineTotal * $taxRate) / 100;
                $lineTotalWithTax = $lineTotal + $lineTax;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $deliveryItem->product_id,
                    'quantity' => $deliveryItem->quantity_delivered,
                    'unit_price' => $deliveryItem->unit_price,
                    'discount_amount' => 0,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotalWithTax,
                    'description' => $deliveryItem->product->name
                ]);

                $subtotal += $lineTotal;
                $totalTax += $lineTax;
            }

            // Update invoice totals
            $invoice->update([
                'subtotal_amount' => $subtotal,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal + $totalTax
            ]);

            DB::commit();

            // Load relationships for response
            $invoice->load([
                'customer:id,name,customer_code',
                'salesOrder:id,sales_order_number',
                'deliveryOrder:id,delivery_order_number',
                'salesInvoiceItems.product:id,name,sku,unit_name'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Invoice generated from delivery order successfully',
                'data' => $invoice
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate invoice from delivery order',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get invoice statistics
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth());
            $dateTo = $request->get('date_to', Carbon::now()->endOfMonth());

            $stats = [
                'total_invoices' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])->count(),
                'total_invoice_value' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])->sum('total_amount'),
                'invoices_by_status' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])
                    ->selectRaw('invoice_status, COUNT(*) as count, SUM(total_amount) as total_value')
                    ->groupBy('invoice_status')
                    ->get(),
                'invoices_by_payment_status' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])
                    ->selectRaw('payment_status, COUNT(*) as count, SUM(total_amount) as total_value')
                    ->groupBy('payment_status')
                    ->get(),
                'overdue_invoices' => SalesInvoice::where('due_date', '<', now())
                    ->where('payment_status', '!=', 'paid')
                    ->count(),
                'overdue_amount' => SalesInvoice::where('due_date', '<', now())
                    ->where('payment_status', '!=', 'paid')
                    ->sum('total_amount'),
                'average_invoice_value' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])->avg('total_amount'),
                'top_customers' => SalesInvoice::whereBetween('invoice_date', [$dateFrom, $dateTo])
                    ->with('customer:id,name,customer_code')
                    ->selectRaw('customer_id, COUNT(*) as invoice_count, SUM(total_amount) as total_value')
                    ->groupBy('customer_id')
                    ->orderByDesc('total_value')
                    ->limit(10)
                    ->get(),
                'aging_analysis' => [
                    'current' => SalesInvoice::where('due_date', '>=', now())
                        ->where('payment_status', '!=', 'paid')
                        ->sum('total_amount'),
                    'days_1_30' => SalesInvoice::whereBetween('due_date', [now()->subDays(30), now()->subDays(1)])
                        ->where('payment_status', '!=', 'paid')
                        ->sum('total_amount'),
                    'days_31_60' => SalesInvoice::whereBetween('due_date', [now()->subDays(60), now()->subDays(31)])
                        ->where('payment_status', '!=', 'paid')
                        ->sum('total_amount'),
                    'days_61_90' => SalesInvoice::whereBetween('due_date', [now()->subDays(90), now()->subDays(61)])
                        ->where('payment_status', '!=', 'paid')
                        ->sum('total_amount'),
                    'over_90_days' => SalesInvoice::where('due_date', '<', now()->subDays(90))
                        ->where('payment_status', '!=', 'paid')
                        ->sum('total_amount')
                ]
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
                'message' => 'Failed to fetch invoice statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery orders available for invoicing
     */
    public function availableDeliveryOrders(Request $request): JsonResponse
    {
        try {
            $query = DeliveryOrder::with([
                'salesOrder.customer:id,name,customer_code',
                'deliveryOrderItems.product:id,name,sku,unit_name'
            ])
                ->where('delivery_status', 'delivered')
                ->whereDoesntHave('salesInvoices');

            if ($request->filled('customer_id')) {
                $query->whereHas('salesOrder', function ($q) use ($request) {
                    $q->where('customer_id', $request->customer_id);
                });
            }

            if ($request->filled('warehouse_id')) {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            $deliveryOrders = $query->orderBy('delivered_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $deliveryOrders
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available delivery orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
