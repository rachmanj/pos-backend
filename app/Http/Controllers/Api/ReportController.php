<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Models\CashSession;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
        // Middleware is handled at route level in Laravel 11+
    }

    /**
     * Get comprehensive dashboard analytics
     */
    public function dashboard(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $request->get('period', 'month');
        $warehouseId = $request->get('warehouse_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Set date range based on period
        if (!$startDate || !$endDate) {
            [$startDate, $endDate] = $this->getDateRange($period);
        }

        $data = [
            'overview' => $this->getOverviewMetrics($startDate, $endDate, $warehouseId),
            'sales_trends' => $this->getSalesTrends($startDate, $endDate, $warehouseId),
            'top_products' => $this->getTopProducts($startDate, $endDate, $warehouseId),
            'top_customers' => $this->getTopCustomers($startDate, $endDate, $warehouseId),
            'warehouse_performance' => $this->getWarehousePerformance($startDate, $endDate),
            'inventory_alerts' => $this->getInventoryAlerts($warehouseId),
            'payment_methods' => $this->getPaymentMethodBreakdown($startDate, $endDate, $warehouseId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'period' => $period,
            ],
        ]);
    }

    /**
     * Get sales analytics and trends
     */
    public function salesAnalytics(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month,quarter',
        ]);

        $period = $request->get('period', 'month');
        $warehouseId = $request->get('warehouse_id');
        $groupBy = $request->get('group_by', 'day');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate || !$endDate) {
            [$startDate, $endDate] = $this->getDateRange($period);
        }

        $data = [
            'sales_summary' => $this->getSalesSummary($startDate, $endDate, $warehouseId),
            'sales_by_period' => $this->getSalesByPeriod($startDate, $endDate, $warehouseId, $groupBy),
            'sales_by_category' => $this->getSalesByCategory($startDate, $endDate, $warehouseId),
            'sales_by_warehouse' => $this->getSalesByWarehouse($startDate, $endDate),
            'hourly_sales' => $this->getHourlySales($startDate, $endDate, $warehouseId),
            'cashier_performance' => $this->getCashierPerformance($startDate, $endDate, $warehouseId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get inventory analytics
     */
    public function inventoryAnalytics(Request $request)
    {
        $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $warehouseId = $request->get('warehouse_id');
        $categoryId = $request->get('category_id');

        $data = [
            'inventory_summary' => $this->getInventorySummary($warehouseId, $categoryId),
            'stock_levels' => $this->getStockLevels($warehouseId, $categoryId),
            'inventory_turnover' => $this->getInventoryTurnover($warehouseId, $categoryId),
            'stock_movements' => $this->getStockMovementAnalytics($warehouseId, $categoryId),
            'low_stock_alerts' => $this->getLowStockAlerts($warehouseId, $categoryId),
            'dead_stock' => $this->getDeadStock($warehouseId, $categoryId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get purchasing analytics
     */
    public function purchasingAnalytics(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $request->get('period', 'month');
        $supplierId = $request->get('supplier_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate || !$endDate) {
            [$startDate, $endDate] = $this->getDateRange($period);
        }

        $data = [
            'purchase_summary' => $this->getPurchaseSummary($startDate, $endDate, $supplierId),
            'supplier_performance' => $this->getSupplierPerformance($startDate, $endDate),
            'purchase_trends' => $this->getPurchaseTrends($startDate, $endDate, $supplierId),
            'cost_analysis' => $this->getCostAnalysis($startDate, $endDate, $supplierId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get financial reports
     */
    public function financialReports(Request $request)
    {
        $request->validate([
            'period' => 'nullable|in:today,week,month,quarter,year',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $period = $request->get('period', 'month');
        $warehouseId = $request->get('warehouse_id');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        if (!$startDate || !$endDate) {
            [$startDate, $endDate] = $this->getDateRange($period);
        }

        $data = [
            'profit_loss' => $this->getProfitLossStatement($startDate, $endDate, $warehouseId),
            'cash_flow' => $this->getCashFlowAnalysis($startDate, $endDate, $warehouseId),
            'tax_summary' => $this->getTaxSummary($startDate, $endDate, $warehouseId),
            'payment_reconciliation' => $this->getPaymentReconciliation($startDate, $endDate, $warehouseId),
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // Private helper methods for data aggregation

    private function getDateRange($period)
    {
        $endDate = Carbon::now();

        switch ($period) {
            case 'today':
                $startDate = Carbon::today();
                break;
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'quarter':
                $startDate = Carbon::now()->startOfQuarter();
                break;
            case 'year':
                $startDate = Carbon::now()->startOfYear();
                break;
            default:
                $startDate = Carbon::now()->startOfMonth();
        }

        return [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')];
    }

    private function getOverviewMetrics($startDate, $endDate, $warehouseId = null)
    {
        $salesQuery = Sale::whereBetween('sale_date', [$startDate, $endDate]);
        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $totalSales = $salesQuery->sum('total_amount');
        $totalTransactions = $salesQuery->count();
        $totalCustomers = $salesQuery->distinct('customer_id')->count('customer_id');

        $purchaseQuery = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate]);
        $totalPurchases = $purchaseQuery->sum('total_amount');

        $inventoryValue = $this->getInventoryValue($warehouseId);

        return [
            'total_sales' => $totalSales,
            'total_transactions' => $totalTransactions,
            'total_customers' => $totalCustomers,
            'total_purchases' => $totalPurchases,
            'inventory_value' => $inventoryValue,
            'average_transaction' => $totalTransactions > 0 ? $totalSales / $totalTransactions : 0,
        ];
    }

    private function getSalesTrends($startDate, $endDate, $warehouseId = null)
    {
        $query = Sale::select(
            DB::raw('DATE(sale_date) as date'),
            DB::raw('COUNT(*) as transactions'),
            DB::raw('SUM(total_amount) as total_sales'),
            DB::raw('AVG(total_amount) as avg_transaction')
        )
            ->whereBetween('sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getTopProducts($startDate, $endDate, $warehouseId = null, $limit = 10)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_amount) as total_revenue'),
                DB::raw('COUNT(DISTINCT sales.id) as transaction_count')
            )
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getTopCustomers($startDate, $endDate, $warehouseId = null, $limit = 10)
    {
        $query = Sale::select(
            'customers.id',
            'customers.name',
            'customers.email',
            DB::raw('COUNT(sales.id) as transaction_count'),
            DB::raw('SUM(sales.total_amount) as total_spent'),
            DB::raw('AVG(sales.total_amount) as avg_transaction')
        )
            ->join('customers', 'sales.customer_id', '=', 'customers.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('customers.id', 'customers.name', 'customers.email')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->get();
    }

    private function getWarehousePerformance($startDate, $endDate)
    {
        return Warehouse::select(
            'warehouses.id',
            'warehouses.name',
            'warehouses.location',
            DB::raw('COUNT(sales.id) as transaction_count'),
            DB::raw('COALESCE(SUM(sales.total_amount), 0) as total_sales'),
            DB::raw('COALESCE(AVG(sales.total_amount), 0) as avg_transaction')
        )
            ->leftJoin('sales', function ($join) use ($startDate, $endDate) {
                $join->on('warehouses.id', '=', 'sales.warehouse_id')
                    ->whereBetween('sales.sale_date', [$startDate, $endDate]);
            })
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.location')
            ->orderBy('total_sales', 'desc')
            ->get();
    }

    private function getInventoryAlerts($warehouseId = null)
    {
        $query = Product::select(
            'products.id',
            'products.name',
            'products.sku',
            'products.minimum_stock',
            DB::raw('COALESCE(SUM(product_stocks.quantity), 0) as current_stock')
        )
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id');

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku', 'products.minimum_stock')
            ->havingRaw('current_stock <= products.minimum_stock')
            ->orderBy('current_stock', 'asc')
            ->get();
    }

    private function getPaymentMethodBreakdown($startDate, $endDate, $warehouseId = null)
    {
        $query = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->select(
                'payment_methods.name',
                'payment_methods.type',
                DB::raw('COUNT(sale_payments.id) as transaction_count'),
                DB::raw('SUM(sale_payments.amount) as total_amount')
            )
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    private function getInventoryValue($warehouseId = null)
    {
        $query = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->select(DB::raw('SUM(product_stocks.quantity * products.selling_price) as total_value'));

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        $result = $query->first();
        return $result ? $result->total_value : 0;
    }

    // Additional helper methods for comprehensive analytics
    private function getSalesSummary($startDate, $endDate, $warehouseId = null)
    {
        $query = Sale::whereBetween('sale_date', [$startDate, $endDate]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return [
            'total_sales' => $query->sum('total_amount'),
            'total_transactions' => $query->count(),
            'total_items_sold' => $query->join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
                ->sum('sale_items.quantity'),
            'average_transaction_value' => $query->avg('total_amount'),
            'total_tax_collected' => $query->sum('tax_amount'),
            'total_discounts_given' => $query->sum('discount_amount'),
        ];
    }

    private function getSalesByPeriod($startDate, $endDate, $warehouseId = null, $groupBy = 'day')
    {
        $dateFormat = match ($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'quarter' => '%Y-Q%q',
            default => '%Y-%m-%d'
        };

        $query = Sale::select(
            DB::raw("DATE_FORMAT(sale_date, '$dateFormat') as period"),
            DB::raw('COUNT(*) as transactions'),
            DB::raw('SUM(total_amount) as total_sales')
        )
            ->whereBetween('sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->groupBy('period')
            ->orderBy('period')
            ->get();
    }

    private function getSalesByCategory($startDate, $endDate, $warehouseId = null)
    {
        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_amount) as total_revenue')
            )
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();
    }

    private function getSalesByWarehouse($startDate, $endDate)
    {
        return Sale::select(
            'warehouses.name as warehouse_name',
            'warehouses.location',
            DB::raw('COUNT(sales.id) as transaction_count'),
            DB::raw('SUM(sales.total_amount) as total_sales'),
            DB::raw('AVG(sales.total_amount) as avg_transaction')
        )
            ->join('warehouses', 'sales.warehouse_id', '=', 'warehouses.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate])
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.location')
            ->orderBy('total_sales', 'desc')
            ->get();
    }

    private function getHourlySales($startDate, $endDate, $warehouseId = null)
    {
        $query = Sale::select(
            DB::raw('HOUR(created_at) as hour'),
            DB::raw('COUNT(*) as transactions'),
            DB::raw('SUM(total_amount) as total_sales')
        )
            ->whereBetween('sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->groupBy('hour')
            ->orderBy('hour')
            ->get();
    }

    private function getCashierPerformance($startDate, $endDate, $warehouseId = null)
    {
        $query = Sale::select(
            'users.name as cashier_name',
            DB::raw('COUNT(sales.id) as transaction_count'),
            DB::raw('SUM(sales.total_amount) as total_sales'),
            DB::raw('AVG(sales.total_amount) as avg_transaction')
        )
            ->join('users', 'sales.created_by', '=', 'users.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('users.id', 'users.name')
            ->orderBy('total_sales', 'desc')
            ->get();
    }

    private function getInventorySummary($warehouseId = null, $categoryId = null)
    {
        $query = DB::table('product_stocks')
            ->join('products', 'product_stocks.product_id', '=', 'products.id');

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return [
            'total_products' => $query->distinct('products.id')->count(),
            'total_stock_value' => $query->sum(DB::raw('product_stocks.quantity * products.selling_price')),
            'total_cost_value' => $query->sum(DB::raw('product_stocks.quantity * products.cost_price')),
            'total_quantity' => $query->sum('product_stocks.quantity'),
        ];
    }

    private function getStockLevels($warehouseId = null, $categoryId = null)
    {
        $query = Product::select(
            'products.id',
            'products.name',
            'products.sku',
            'products.minimum_stock',
            'categories.name as category_name',
            DB::raw('COALESCE(SUM(product_stocks.quantity), 0) as current_stock')
        )
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.id');

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku', 'products.minimum_stock', 'categories.name')
            ->orderBy('current_stock', 'asc')
            ->get();
    }

    private function getInventoryTurnover($warehouseId = null, $categoryId = null)
    {
        // Calculate inventory turnover for the last 12 months
        $startDate = Carbon::now()->subMonths(12)->format('Y-m-d');
        $endDate = Carbon::now()->format('Y-m-d');

        $query = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_sold'),
                DB::raw('AVG(product_stocks.quantity) as avg_stock'),
                DB::raw('CASE WHEN AVG(product_stocks.quantity) > 0 THEN SUM(sale_items.quantity) / AVG(product_stocks.quantity) ELSE 0 END as turnover_ratio')
            )
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId)
                ->where('product_stocks.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('turnover_ratio', 'desc')
            ->get();
    }

    private function getStockMovementAnalytics($warehouseId = null, $categoryId = null)
    {
        $query = StockMovement::select(
            'stock_movements.movement_type',
            DB::raw('COUNT(*) as movement_count'),
            DB::raw('SUM(stock_movements.quantity) as total_quantity'),
            DB::raw('AVG(stock_movements.quantity) as avg_quantity')
        )
            ->join('products', 'stock_movements.product_id', '=', 'products.id');

        if ($warehouseId) {
            $query->where('stock_movements.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->groupBy('stock_movements.movement_type')
            ->get();
    }

    private function getLowStockAlerts($warehouseId = null, $categoryId = null)
    {
        $query = Product::select(
            'products.id',
            'products.name',
            'products.sku',
            'products.minimum_stock',
            'categories.name as category_name',
            DB::raw('COALESCE(SUM(product_stocks.quantity), 0) as current_stock'),
            DB::raw('products.minimum_stock - COALESCE(SUM(product_stocks.quantity), 0) as shortage')
        )
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->join('categories', 'products.category_id', '=', 'categories.id');

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku', 'products.minimum_stock', 'categories.name')
            ->havingRaw('current_stock <= products.minimum_stock')
            ->orderBy('shortage', 'desc')
            ->get();
    }

    private function getDeadStock($warehouseId = null, $categoryId = null)
    {
        // Products with no sales in the last 90 days
        $cutoffDate = Carbon::now()->subDays(90)->format('Y-m-d');

        $query = Product::select(
            'products.id',
            'products.name',
            'products.sku',
            'categories.name as category_name',
            DB::raw('COALESCE(SUM(product_stocks.quantity), 0) as current_stock'),
            DB::raw('MAX(sales.sale_date) as last_sale_date')
        )
            ->leftJoin('product_stocks', 'products.id', '=', 'product_stocks.product_id')
            ->leftJoin('sale_items', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('categories', 'products.category_id', '=', 'categories.id');

        if ($warehouseId) {
            $query->where('product_stocks.warehouse_id', $warehouseId);
        }

        if ($categoryId) {
            $query->where('products.category_id', $categoryId);
        }

        return $query->groupBy('products.id', 'products.name', 'products.sku', 'categories.name')
            ->havingRaw('MAX(sales.sale_date) < ? OR MAX(sales.sale_date) IS NULL', [$cutoffDate])
            ->havingRaw('current_stock > 0')
            ->orderBy('last_sale_date', 'asc')
            ->get();
    }

    private function getPurchaseSummary($startDate, $endDate, $supplierId = null)
    {
        $query = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return [
            'total_orders' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'average_order_value' => $query->avg('total_amount'),
            'pending_orders' => $query->where('status', 'pending')->count(),
            'completed_orders' => $query->where('status', 'completed')->count(),
        ];
    }

    private function getSupplierPerformance($startDate, $endDate)
    {
        return Supplier::select(
            'suppliers.id',
            'suppliers.name',
            'suppliers.email',
            DB::raw('COUNT(purchase_orders.id) as order_count'),
            DB::raw('COALESCE(SUM(purchase_orders.total_amount), 0) as total_amount'),
            DB::raw('COALESCE(AVG(purchase_orders.total_amount), 0) as avg_order_value')
        )
            ->leftJoin('purchase_orders', function ($join) use ($startDate, $endDate) {
                $join->on('suppliers.id', '=', 'purchase_orders.supplier_id')
                    ->whereBetween('purchase_orders.order_date', [$startDate, $endDate]);
            })
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.email')
            ->orderBy('total_amount', 'desc')
            ->get();
    }

    private function getPurchaseTrends($startDate, $endDate, $supplierId = null)
    {
        $query = PurchaseOrder::select(
            DB::raw('DATE(order_date) as date'),
            DB::raw('COUNT(*) as order_count'),
            DB::raw('SUM(total_amount) as total_amount')
        )
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        return $query->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getCostAnalysis($startDate, $endDate, $supplierId = null)
    {
        $query = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_order_items.purchase_order_id', '=', 'purchase_orders.id')
            ->join('products', 'purchase_order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'categories.name as category_name',
                DB::raw('SUM(purchase_order_items.quantity) as total_quantity'),
                DB::raw('SUM(purchase_order_items.total_amount) as total_cost'),
                DB::raw('AVG(purchase_order_items.unit_price) as avg_unit_price')
            )
            ->whereBetween('purchase_orders.order_date', [$startDate, $endDate]);

        if ($supplierId) {
            $query->where('purchase_orders.supplier_id', $supplierId);
        }

        return $query->groupBy('categories.id', 'categories.name')
            ->orderBy('total_cost', 'desc')
            ->get();
    }

    private function getProfitLossStatement($startDate, $endDate, $warehouseId = null)
    {
        // Revenue from sales
        $salesQuery = Sale::whereBetween('sale_date', [$startDate, $endDate]);
        if ($warehouseId) {
            $salesQuery->where('warehouse_id', $warehouseId);
        }

        $revenue = $salesQuery->sum('total_amount');
        $taxCollected = $salesQuery->sum('tax_amount');
        $discountsGiven = $salesQuery->sum('discount_amount');

        // Cost of goods sold
        $cogsQuery = DB::table('sale_items')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $cogsQuery->where('sales.warehouse_id', $warehouseId);
        }

        $cogs = $cogsQuery->sum(DB::raw('sale_items.quantity * products.cost_price'));

        // Purchase costs
        $purchaseCosts = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->sum('total_amount');

        return [
            'revenue' => $revenue,
            'cost_of_goods_sold' => $cogs,
            'gross_profit' => $revenue - $cogs,
            'gross_profit_margin' => $revenue > 0 ? (($revenue - $cogs) / $revenue) * 100 : 0,
            'tax_collected' => $taxCollected,
            'discounts_given' => $discountsGiven,
            'purchase_costs' => $purchaseCosts,
            'net_profit' => $revenue - $cogs - $discountsGiven,
        ];
    }

    private function getCashFlowAnalysis($startDate, $endDate, $warehouseId = null)
    {
        // Cash inflows from sales
        $cashInflowQuery = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $cashInflowQuery->where('sales.warehouse_id', $warehouseId);
        }

        $cashInflows = $cashInflowQuery->sum('sale_payments.amount');

        // Cash outflows from purchases
        $cashOutflows = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate])
            ->sum('total_amount');

        return [
            'cash_inflows' => $cashInflows,
            'cash_outflows' => $cashOutflows,
            'net_cash_flow' => $cashInflows - $cashOutflows,
        ];
    }

    private function getTaxSummary($startDate, $endDate, $warehouseId = null)
    {
        $query = Sale::whereBetween('sale_date', [$startDate, $endDate]);
        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return [
            'total_tax_collected' => $query->sum('tax_amount'),
            'taxable_sales' => $query->sum('subtotal_amount'),
            'tax_rate' => 11, // Indonesian PPN rate
            'transaction_count' => $query->count(),
        ];
    }

    private function getPaymentReconciliation($startDate, $endDate, $warehouseId = null)
    {
        $query = DB::table('sale_payments')
            ->join('sales', 'sale_payments.sale_id', '=', 'sales.id')
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->select(
                'payment_methods.name',
                'payment_methods.type',
                DB::raw('COUNT(sale_payments.id) as transaction_count'),
                DB::raw('SUM(sale_payments.amount) as total_amount')
            )
            ->whereBetween('sales.sale_date', [$startDate, $endDate]);

        if ($warehouseId) {
            $query->where('sales.warehouse_id', $warehouseId);
        }

        return $query->groupBy('payment_methods.id', 'payment_methods.name', 'payment_methods.type')
            ->orderBy('total_amount', 'desc')
            ->get();
    }
}
