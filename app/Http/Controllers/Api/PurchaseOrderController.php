<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PurchaseOrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier', 'creator', 'approver']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('date_from')) {
            $query->where('order_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('order_date', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->get('sort_by', 'order_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $purchaseOrders = $query->paginate($perPage);

        return response()->json([
            'data' => PurchaseOrderResource::collection($purchaseOrders->items()),
            'meta' => [
                'current_page' => $purchaseOrders->currentPage(),
                'from' => $purchaseOrders->firstItem(),
                'last_page' => $purchaseOrders->lastPage(),
                'per_page' => $purchaseOrders->perPage(),
                'to' => $purchaseOrders->lastItem(),
                'total' => $purchaseOrders->total(),
            ]
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request) {
            $purchaseOrder = PurchaseOrder::create([
                'supplier_id' => $request->supplier_id,
                'created_by' => Auth::id(),
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions,
                'status' => 'draft',
            ]);

            foreach ($request->items as $itemData) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $itemData['product_id'],
                    'unit_id' => $itemData['unit_id'],
                    'quantity_ordered' => $itemData['quantity_ordered'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['quantity_ordered'] * $itemData['unit_price'],
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            $purchaseOrder->calculateTotals();
            $purchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order created successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ], 201);
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $purchaseOrder->load([
            'supplier',
            'creator',
            'approver',
            'items.product',
            'items.unit',
            'receipts'
        ]);

        return response()->json([
            'data' => new PurchaseOrderResource($purchaseOrder)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeEdited()) {
            return response()->json([
                'message' => 'Purchase order cannot be edited in current status'
            ], 422);
        }

        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after:order_date',
            'notes' => 'nullable|string',
            'terms_conditions' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:purchase_order_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.unit_id' => 'required|exists:units,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($request, $purchaseOrder) {
            $purchaseOrder->update([
                'supplier_id' => $request->supplier_id,
                'order_date' => $request->order_date,
                'expected_delivery_date' => $request->expected_delivery_date,
                'notes' => $request->notes,
                'terms_conditions' => $request->terms_conditions,
            ]);

            // Get existing item IDs
            $existingItemIds = $purchaseOrder->items->pluck('id')->toArray();
            $submittedItemIds = collect($request->items)->pluck('id')->filter()->toArray();

            // Delete items not in the submitted list
            $itemsToDelete = array_diff($existingItemIds, $submittedItemIds);
            PurchaseOrderItem::whereIn('id', $itemsToDelete)->delete();

            // Update or create items
            foreach ($request->items as $itemData) {
                $itemData['purchase_order_id'] = $purchaseOrder->id;
                $itemData['total_price'] = $itemData['quantity_ordered'] * $itemData['unit_price'];

                if (isset($itemData['id'])) {
                    $item = PurchaseOrderItem::find($itemData['id']);
                    $item->update($itemData);
                } else {
                    PurchaseOrderItem::create($itemData);
                }
            }

            $purchaseOrder->calculateTotals();
            $purchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order updated successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeDeleted()) {
            return response()->json([
                'message' => 'Purchase order cannot be deleted in current status'
            ], 422);
        }

        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase order deleted successfully'
        ]);
    }

    public function approve(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeApproved()) {
            return response()->json([
                'message' => 'Purchase order cannot be approved in current status'
            ], 422);
        }

        $success = $purchaseOrder->approve(Auth::user());

        if ($success) {
            $purchaseOrder->load(['supplier', 'creator', 'approver']);

            return response()->json([
                'message' => 'Purchase order approved successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        }

        return response()->json([
            'message' => 'Failed to approve purchase order'
        ], 422);
    }

    public function submitForApproval(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft purchase orders can be submitted for approval'
            ], 422);
        }

        if ($purchaseOrder->items->isEmpty()) {
            return response()->json([
                'message' => 'Purchase order must have at least one item'
            ], 422);
        }

        $purchaseOrder->update(['status' => 'pending_approval']);

        return response()->json([
            'message' => 'Purchase order submitted for approval successfully',
            'data' => new PurchaseOrderResource($purchaseOrder)
        ]);
    }

    public function cancel(PurchaseOrder $purchaseOrder): JsonResponse
    {
        if (!$purchaseOrder->canBeCancelled()) {
            return response()->json([
                'message' => 'Purchase order cannot be cancelled in current status'
            ], 422);
        }

        $success = $purchaseOrder->cancel();

        if ($success) {
            return response()->json([
                'message' => 'Purchase order cancelled successfully',
                'data' => new PurchaseOrderResource($purchaseOrder)
            ]);
        }

        return response()->json([
            'message' => 'Failed to cancel purchase order'
        ], 422);
    }

    public function duplicate(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return DB::transaction(function () use ($purchaseOrder) {
            $newPurchaseOrder = $purchaseOrder->replicate();
            $newPurchaseOrder->po_number = null; // Will be auto-generated
            $newPurchaseOrder->status = 'draft';
            $newPurchaseOrder->created_by = Auth::id();
            $newPurchaseOrder->approved_by = null;
            $newPurchaseOrder->approved_date = null;
            $newPurchaseOrder->order_date = now()->toDateString();
            $newPurchaseOrder->save();

            foreach ($purchaseOrder->items as $item) {
                $newItem = $item->replicate();
                $newItem->purchase_order_id = $newPurchaseOrder->id;
                $newItem->quantity_received = 0;
                $newItem->save();
            }

            $newPurchaseOrder->calculateTotals();
            $newPurchaseOrder->load(['supplier', 'creator', 'items.product', 'items.unit']);

            return response()->json([
                'message' => 'Purchase order duplicated successfully',
                'data' => new PurchaseOrderResource($newPurchaseOrder)
            ], 201);
        });
    }

    public function analytics(): JsonResponse
    {
        $totalOrders = PurchaseOrder::count();
        $pendingApproval = PurchaseOrder::where('status', 'pending_approval')->count();
        $pendingDelivery = PurchaseOrder::whereIn('status', ['approved', 'sent_to_supplier', 'partially_received'])->count();
        $totalAmount = PurchaseOrder::sum('total_amount');

        // Monthly stats (SQLite compatible)
        $monthlyStats = PurchaseOrder::selectRaw('
                CAST(strftime("%m", order_date) AS INTEGER) as month,
                COUNT(*) as orders,
                SUM(total_amount) as amount
            ')
            ->whereYear('order_date', now()->year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Top suppliers
        $topSuppliers = PurchaseOrder::with('supplier')
            ->selectRaw('supplier_id, COUNT(*) as orders, SUM(total_amount) as amount')
            ->groupBy('supplier_id')
            ->orderBy('amount', 'desc')
            ->limit(5)
            ->get();

        // Status breakdown
        $statusBreakdown = PurchaseOrder::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        $stats = [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'pending_approval' => $pendingApproval,
            'pending_delivery' => $pendingDelivery,
            'monthly_stats' => $monthlyStats,
            'top_suppliers' => $topSuppliers,
            'status_breakdown' => $statusBreakdown,
        ];

        return response()->json([
            'data' => $stats
        ]);
    }

    /**
     * Download purchase order as PDF
     */
    public function downloadPDF(PurchaseOrder $purchaseOrder)
    {
        try {
            // Load relationships needed for PDF
            $purchaseOrder->load([
                'supplier',
                'items.product.unit',
                'creator',
                'approver'
            ]);

            // Generate PDF content
            $pdf = $this->generatePurchaseOrderPDF($purchaseOrder);

            $filename = "PO-{$purchaseOrder->po_number}.pdf";

            return response($pdf, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Content-Length' => strlen($pdf),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF content for purchase order
     */
    private function generatePurchaseOrderPDF(PurchaseOrder $purchaseOrder): string
    {
        // Simple HTML to PDF conversion
        $html = $this->generatePurchaseOrderHTML($purchaseOrder);

        // For now, return HTML content as PDF placeholder
        // In production, you would use a proper PDF library like DomPDF or wkhtmltopdf
        return $html;
    }

    /**
     * Generate HTML content for purchase order
     */
    private function generatePurchaseOrderHTML(PurchaseOrder $purchaseOrder): string
    {
        $subtotal = $purchaseOrder->items->sum(function ($item) {
            return $item->quantity_ordered * $item->unit_price;
        });

        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Purchase Order {$purchaseOrder->po_number}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .company-info { margin-bottom: 20px; }
                .po-details { display: flex; justify-content: space-between; margin-bottom: 20px; }
                .supplier-info { margin-bottom: 20px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .total-section { text-align: right; margin-top: 20px; }
                .footer { margin-top: 30px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>PURCHASE ORDER</h1>
                <h2>Sarange ERP</h2>
                <p>Enterprise Resource Planning System</p>
            </div>

            <div class='po-details'>
                <div>
                    <strong>PO Number:</strong> {$purchaseOrder->po_number}<br>
                    <strong>Order Date:</strong> " . date('M d, Y', strtotime($purchaseOrder->order_date)) . "<br>
                    <strong>Expected Delivery:</strong> " . ($purchaseOrder->expected_delivery_date ? date('M d, Y', strtotime($purchaseOrder->expected_delivery_date)) : 'Not specified') . "
                </div>
                <div>
                    <strong>Status:</strong> " . ucwords(str_replace('_', ' ', $purchaseOrder->status)) . "<br>
                    <strong>Created By:</strong> {$purchaseOrder->creator->name}<br>
                    " . ($purchaseOrder->approver ? "<strong>Approved By:</strong> {$purchaseOrder->approver->name}" : '') . "
                </div>
            </div>

            <div class='supplier-info'>
                <h3>Supplier Information</h3>
                <strong>{$purchaseOrder->supplier->name}</strong><br>
                Code: {$purchaseOrder->supplier->code}<br>
                " . ($purchaseOrder->supplier->email ? "Email: {$purchaseOrder->supplier->email}<br>" : '') . "
                " . ($purchaseOrder->supplier->phone ? "Phone: {$purchaseOrder->supplier->phone}<br>" : '') . "
                " . ($purchaseOrder->supplier->address ? "Address: {$purchaseOrder->supplier->address}" : '') . "
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>SKU</th>
                        <th>Unit</th>
                        <th>Qty Ordered</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>";

        foreach ($purchaseOrder->items as $item) {
            $itemTotal = $item->quantity_ordered * $item->unit_price;
            $html .= "
                    <tr>
                        <td>{$item->product->name}</td>
                        <td>{$item->product->sku}</td>
                        <td>{$item->product->unit->name}</td>
                        <td>" . number_format($item->quantity_ordered, 2) . "</td>
                        <td>Rp " . number_format($item->unit_price, 0, ',', '.') . "</td>
                        <td>Rp " . number_format($itemTotal, 0, ',', '.') . "</td>
                    </tr>";
        }

        $html .= "
                </tbody>
            </table>

            <div class='total-section'>
                <p><strong>Subtotal: Rp " . number_format($subtotal, 0, ',', '.') . "</strong></p>
                <p><strong>Tax: Rp " . number_format($purchaseOrder->tax_amount, 0, ',', '.') . "</strong></p>
                <p><strong>Total Amount: Rp " . number_format($purchaseOrder->total_amount, 0, ',', '.') . "</strong></p>
            </div>

            " . ($purchaseOrder->notes ? "
            <div class='notes'>
                <h3>Notes</h3>
                <p>{$purchaseOrder->notes}</p>
            </div>" : '') . "

            <div class='footer'>
                <p>Generated on " . date('M d, Y H:i:s') . " by Sarange ERP System</p>
                <p>This is a computer-generated document and does not require a signature.</p>
            </div>
        </body>
        </html>";

        return $html;
    }
}
