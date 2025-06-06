<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Product;
use App\Models\WarehouseStock;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'user', 'warehouse', 'cashSession', 'saleItems.product']);

        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by customer
        if ($request->has('customer_id') && $request->customer_id) {
            $query->where('customer_id', $request->customer_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by payment status
        if ($request->has('payment_status') && $request->payment_status !== '') {
            $query->where('payment_status', $request->payment_status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('sale_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('sale_date', '<=', $request->date_to);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('sale_number', 'like', '%' . $request->search . '%')
                    ->orWhereHas('customer', function ($customerQuery) use ($request) {
                        $customerQuery->where('name', 'like', '%' . $request->search . '%');
                    });
            });
        }

        // Sort options
        $sortField = $request->get('sort_field', 'sale_date');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $sales = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'customer_id' => 'nullable|exists:customers,id',
            'cash_session_id' => 'nullable|exists:cash_sessions,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => 'required|exists:payment_methods,id',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference_number' => 'nullable|string|max:255',
            'discount_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Check stock availability for all items
            foreach ($validated['items'] as $item) {
                $stock = WarehouseStock::where('warehouse_id', $validated['warehouse_id'])
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$stock || $stock->quantity < $item['quantity']) {
                    $product = Product::find($item['product_id']);
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }
            }

            // Calculate totals
            $subtotalAmount = 0;
            $totalTaxAmount = $validated['tax_amount'] ?? 0;
            $totalDiscountAmount = $validated['discount_amount'] ?? 0;

            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = $item['discount_amount'] ?? 0;
                $subtotalAmount += ($lineTotal - $lineDiscount);
            }

            $totalAmount = $subtotalAmount + $totalTaxAmount - $totalDiscountAmount;

            // Validate payment amounts
            $totalPayments = array_sum(array_column($validated['payments'], 'amount'));
            if (abs($totalPayments - $totalAmount) > 0.01) {
                throw new \Exception('Payment amount does not match total amount');
            }

            // Generate sale number
            $saleNumber = $this->generateSaleNumber($validated['warehouse_id']);

            // Create sale
            $sale = Sale::create([
                'sale_number' => $saleNumber,
                'warehouse_id' => $validated['warehouse_id'],
                'customer_id' => $validated['customer_id'],
                'cash_session_id' => $validated['cash_session_id'],
                'user_id' => Auth::id(),
                'sale_date' => now(),
                'subtotal_amount' => $subtotalAmount,
                'tax_amount' => $totalTaxAmount,
                'discount_amount' => $totalDiscountAmount,
                'total_amount' => $totalAmount,
                'payment_status' => 'paid',
                'status' => 'completed',
                'notes' => $validated['notes']
            ]);

            // Create sale items and update stock
            foreach ($validated['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_price'];
                $lineDiscount = $item['discount_amount'] ?? 0;
                $lineTax = ($item['tax_rate'] ?? 0) / 100 * ($lineTotal - $lineDiscount);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_amount' => $lineDiscount,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineTotal - $lineDiscount + $lineTax
                ]);

                // Update warehouse stock
                $stock = WarehouseStock::where('warehouse_id', $validated['warehouse_id'])
                    ->where('product_id', $item['product_id'])
                    ->first();

                $stock->decrement('quantity', $item['quantity']);

                // Update product stock (global)
                $product = Product::find($item['product_id']);
                $product->decrement('stock_quantity', $item['quantity']);
            }

            // Create sale payments
            foreach ($validated['payments'] as $payment) {
                SalePayment::create([
                    'sale_id' => $sale->id,
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => $payment['amount'],
                    'reference_number' => $payment['reference_number'] ?? null,
                    'payment_date' => now()
                ]);
            }

            // Update customer statistics if customer is specified
            if ($validated['customer_id']) {
                $customer = Customer::find($validated['customer_id']);
                $customer->increment('total_spent', $totalAmount);
                $customer->increment('total_orders');
                $customer->update(['last_purchase_date' => now()]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sale->load(['customer', 'saleItems.product', 'salePayments.paymentMethod']),
                'message' => 'Sale completed successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Sale $sale): JsonResponse
    {
        $sale->load([
            'customer',
            'user',
            'warehouse',
            'cashSession',
            'saleItems.product',
            'salePayments.paymentMethod'
        ]);

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function void(Sale $sale): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check if sale can be voided
            if ($sale->status === 'voided') {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale is already voided'
                ], 422);
            }

            // Restore stock for all items
            foreach ($sale->saleItems as $item) {
                // Restore warehouse stock
                $stock = WarehouseStock::where('warehouse_id', $sale->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $stock->increment('quantity', $item->quantity);
                }

                // Restore product stock (global)
                $item->product->increment('stock_quantity', $item->quantity);
            }

            // Update customer statistics if customer exists
            if ($sale->customer) {
                $sale->customer->decrement('total_spent', $sale->total_amount);
                $sale->customer->decrement('total_orders');
            }

            // Update sale status
            $sale->update([
                'status' => 'voided',
                'voided_at' => now(),
                'voided_by' => Auth::id()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $sale->fresh(),
                'message' => 'Sale voided successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to void sale',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDailySummary(Request $request): JsonResponse
    {
        $date = $request->get('date', now()->toDateString());
        $warehouseId = $request->get('warehouse_id');

        $query = Sale::whereDate('sale_date', $date)
            ->where('status', 'completed');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $sales = $query->with(['salePayments.paymentMethod'])->get();

        $summary = [
            'date' => $date,
            'total_sales_count' => $sales->count(),
            'total_sales_amount' => $sales->sum('total_amount'),
            'total_tax_amount' => $sales->sum('tax_amount'),
            'total_discount_amount' => $sales->sum('discount_amount'),
            'payment_breakdown' => []
        ];

        // Group payments by method
        $paymentMethods = PaymentMethod::all()->keyBy('id');
        foreach ($paymentMethods as $method) {
            $total = $sales->flatMap->salePayments
                ->where('payment_method_id', $method->id)
                ->sum('amount');

            if ($total > 0) {
                $summary['payment_breakdown'][] = [
                    'payment_method' => $method->name,
                    'total_amount' => $total
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $search = $request->get('search', '');

        $query = Product::query()
            ->where('status', 'active')
            ->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('sku', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%');
            });

        if ($warehouseId) {
            $query->whereHas('warehouseStocks', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->where('quantity', '>', 0);
            });

            $products = $query->with([
                'category',
                'unit',
                'warehouseStocks' => function ($q) use ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            ])->limit(20)->get();

            // Add available stock to each product
            $products->each(function ($product) {
                $stock = $product->warehouseStocks->first();
                $product->available_stock = $stock ? $stock->quantity : 0;
                unset($product->warehouseStocks);
            });
        } else {
            $products = $query->with(['category', 'unit', 'stock'])->limit(20)->get();
            $products->each(function ($product) {
                $product->available_stock = $product->stock?->current_stock ?? 0;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    private function generateSaleNumber($warehouseId): string
    {
        $date = now()->format('Ymd');
        $warehouse = \App\Models\Warehouse::find($warehouseId);
        $prefix = $warehouse->code ?? 'WH';

        $lastSale = Sale::where('warehouse_id', $warehouseId)
            ->whereDate('sale_date', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastSale ? (int)substr($lastSale->sale_number, -4) + 1 : 1;

        return "{$prefix}-{$date}-" . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
}
