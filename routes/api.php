<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseReceiptController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\WarehouseZoneController;
use App\Http\Controllers\Api\WarehouseStockController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Handle CORS preflight requests
Route::options('{any}', function (Request $request) {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, X-Requested-With, Origin')
        ->header('Access-Control-Max-Age', '86400');
})->where('any', '.*');

// Simple test endpoint for connectivity testing
Route::get('test', function () {
    return response()->json([
        'message' => 'API is working',
        'timestamp' => now(),
        'server' => 'Laravel POS-ATK Backend'
    ]);
});

// Public authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'user']);
        Route::post('refresh', [AuthController::class, 'refresh']);
    });

    // User management routes (with permissions)
    Route::middleware('permission:view users')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{userId}', [UserController::class, 'show']);
    });

    Route::middleware('permission:create users')->group(function () {
        Route::post('users', [UserController::class, 'store']);
    });

    Route::middleware('permission:edit users')->group(function () {
        Route::put('users/{userId}', [UserController::class, 'update']);
        Route::patch('users/{userId}', [UserController::class, 'update']);
    });

    Route::middleware('permission:delete users')->group(function () {
        Route::delete('users/{userId}', [UserController::class, 'destroy']);
    });

    Route::middleware('permission:assign roles')->group(function () {
        Route::post('users/{userId}/assign-role', [UserController::class, 'assignRole']);
        Route::post('users/{userId}/remove-role', [UserController::class, 'removeRole']);
        Route::post('users/bulk-assign-role', [UserController::class, 'bulkAssignRole']);
    });

    // Role management routes (super-admin only)
    Route::middleware('role:super-admin')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::get('roles/{roleId}', [RoleController::class, 'show']);
        Route::put('roles/{roleId}', [RoleController::class, 'update']);
        Route::delete('roles/{roleId}', [RoleController::class, 'destroy']);
        Route::get('permissions', [RoleController::class, 'permissions']);
    });

    // Inventory Management Routes

    // Product reports - must come first to avoid route conflicts
    Route::middleware(['auth:sanctum', 'permission:manage inventory|view reports'])->group(function () {
        Route::get('products/low-stock', [ProductController::class, 'lowStock']);
    });

    // Categories - accessible to inventory viewers
    Route::middleware('permission:view inventory|manage inventory')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/tree', [CategoryController::class, 'tree']);
        Route::get('categories/{category}', [CategoryController::class, 'show']);
        Route::get('categories/{category}/children', [CategoryController::class, 'children']);
    });

    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    });

    // Units - accessible to inventory viewers
    Route::middleware('permission:view inventory|manage inventory')->group(function () {
        Route::get('units', [UnitController::class, 'index']);
        Route::get('units/base', [UnitController::class, 'baseUnits']);
        Route::get('units/conversion', [UnitController::class, 'conversion']);
        Route::post('units/convert', [UnitController::class, 'conversion']);
        Route::get('units/{unit}', [UnitController::class, 'show']);
    });

    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('units', [UnitController::class, 'store']);
        Route::put('units/{unit}', [UnitController::class, 'update']);
        Route::delete('units/{unit}', [UnitController::class, 'destroy']);
    });

    // Suppliers - accessible to purchasing and inventory management (not basic inventory view)
    Route::middleware('permission:manage inventory|view purchasing|manage purchasing')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index']);
        Route::get('suppliers/active', [SupplierController::class, 'active']);
        Route::get('suppliers/search/{query}', [SupplierController::class, 'search']);
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::get('suppliers/{supplier}/performance', [SupplierController::class, 'performance']);
    });

    Route::middleware('permission:manage purchasing')->group(function () {
        Route::post('suppliers', [SupplierController::class, 'store']);
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update']);
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy']);
    });

    // Products - core inventory functionality
    Route::middleware('permission:view inventory|manage inventory|process sales')->group(function () {
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/search/{query}', [ProductController::class, 'search']);
        Route::get('products/barcode/{barcode}', [ProductController::class, 'findByBarcode']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::get('products/{product}/stock-history', [ProductController::class, 'stockHistory']);
    });

    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::post('products/bulk-update', [ProductController::class, 'bulkUpdate']);
    });

    // Stock Movements - inventory tracking
    Route::middleware('permission:view inventory|manage inventory')->group(function () {
        Route::get('stock-movements', [StockMovementController::class, 'index']);
        Route::get('stock-movements/statistics', [StockMovementController::class, 'statistics']);
        Route::get('stock-movements/{stockMovement}', [StockMovementController::class, 'show']);
        Route::get('stock-movements/product/{product}', [StockMovementController::class, 'byProduct']);
    });

    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('stock-movements', [StockMovementController::class, 'store']);
        Route::post('stock-movements/adjustment', [StockMovementController::class, 'adjustment']);
        Route::post('stock-movements/bulk-adjustment', [StockMovementController::class, 'bulkAdjustment']);
    });

    // Stock queries and reports
    Route::middleware('permission:view inventory|manage inventory|view reports')->group(function () {
        Route::get('inventory/dashboard', [ProductController::class, 'dashboard']);
        Route::get('inventory/stock-levels', [ProductController::class, 'stockLevels']);
        Route::get('inventory/stock-alerts', [ProductController::class, 'stockAlerts']);
        Route::get('inventory/valuation', [ProductController::class, 'valuation']);
    });

    // Purchasing Management Routes

    // Purchase Orders - view access for purchasing viewers
    Route::middleware('permission:view purchasing|manage purchasing|approve purchase orders')->group(function () {
        Route::get('purchase-orders', [PurchaseOrderController::class, 'index']);
        Route::get('purchase-orders/analytics', [PurchaseOrderController::class, 'analytics']);
        Route::get('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'show']);
    });

    // Purchase Orders - management access
    Route::middleware('permission:manage purchasing')->group(function () {
        Route::post('purchase-orders', [PurchaseOrderController::class, 'store']);
        Route::put('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update']);
        Route::delete('purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'destroy']);
        Route::post('purchase-orders/{purchaseOrder}/submit-for-approval', [PurchaseOrderController::class, 'submitForApproval']);
        Route::post('purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
        Route::post('purchase-orders/{purchaseOrder}/duplicate', [PurchaseOrderController::class, 'duplicate']);
    });

    // Purchase Orders - approval access
    Route::middleware('permission:approve purchase orders')->group(function () {
        Route::post('purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    });

    // Purchase Receipts - view access for purchasing and warehouse staff
    Route::middleware('permission:view purchasing|manage purchasing|receive goods')->group(function () {
        Route::get('purchase-receipts', [PurchaseReceiptController::class, 'index']);
        Route::get('purchase-receipts/analytics', [PurchaseReceiptController::class, 'analytics']);
        Route::get('purchase-receipts/{purchaseReceipt}', [PurchaseReceiptController::class, 'show']);
        Route::get('purchase-orders/{purchaseOrder}/receivable-items', [PurchaseReceiptController::class, 'getReceivableItems']);
    });

    // Purchase Receipts - management and receiving
    Route::middleware('permission:receive goods|manage purchasing')->group(function () {
        Route::post('purchase-receipts', [PurchaseReceiptController::class, 'store']);
        Route::put('purchase-receipts/{purchaseReceipt}', [PurchaseReceiptController::class, 'update']);
        Route::delete('purchase-receipts/{purchaseReceipt}', [PurchaseReceiptController::class, 'destroy']);
    });

    // Purchase Receipts - approval and stock update
    Route::middleware('permission:approve purchase receipts|manage inventory')->group(function () {
        Route::post('purchase-receipts/{purchaseReceipt}/approve', [PurchaseReceiptController::class, 'approve']);
        Route::post('purchase-receipts/{purchaseReceipt}/update-stock', [PurchaseReceiptController::class, 'updateStock']);
    });

    // Warehouse Management Routes

    // Warehouses - view access for inventory and warehouse staff
    Route::middleware('permission:view inventory|manage inventory|manage warehouses|view warehouses')->group(function () {
        Route::get('warehouses', [WarehouseController::class, 'index']);
        Route::get('warehouses/analytics', [WarehouseController::class, 'globalAnalytics']);
        Route::get('warehouses/active', [WarehouseController::class, 'getActiveWarehouses']);
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show']);
        Route::get('warehouses/{warehouse}/analytics', [WarehouseController::class, 'analytics']);
    });

    // Warehouses - management access
    Route::middleware('permission:manage warehouses')->group(function () {
        Route::post('warehouses', [WarehouseController::class, 'store']);
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update']);
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy']);
        Route::post('warehouses/{warehouse}/set-default', [WarehouseController::class, 'setDefault']);
    });

    // Warehouse Zones - view access
    Route::middleware('permission:view inventory|manage inventory|manage warehouses|view warehouses')->group(function () {
        Route::get('warehouse-zones', [WarehouseZoneController::class, 'index']);
        Route::get('warehouse-zones/{warehouseZone}', [WarehouseZoneController::class, 'show']);
        Route::get('warehouses/{warehouse}/zones', [WarehouseZoneController::class, 'byWarehouse']);
    });

    // Warehouse Zones - management access
    Route::middleware('permission:manage warehouses')->group(function () {
        Route::post('warehouse-zones', [WarehouseZoneController::class, 'store']);
        Route::put('warehouse-zones/{warehouseZone}', [WarehouseZoneController::class, 'update']);
        Route::delete('warehouse-zones/{warehouseZone}', [WarehouseZoneController::class, 'destroy']);
    });

    // Warehouse Stock - view access
    Route::middleware('permission:view inventory|manage inventory|view warehouses')->group(function () {
        Route::get('warehouse-stocks', [WarehouseStockController::class, 'index']);
        Route::get('warehouse-stocks/{warehouseStock}', [WarehouseStockController::class, 'show']);
        Route::get('warehouses/{warehouse}/stocks', [WarehouseStockController::class, 'byWarehouse']);
        Route::get('products/{product}/warehouse-stocks', [WarehouseStockController::class, 'byProduct']);
    });

    // Warehouse Stock - management access
    Route::middleware('permission:manage inventory')->group(function () {
        Route::post('warehouse-stocks/adjust', [WarehouseStockController::class, 'adjust']);
        Route::post('warehouse-stocks/transfer', [WarehouseStockController::class, 'transfer']);
        Route::post('warehouse-stocks/reserve', [WarehouseStockController::class, 'reserve']);
        Route::post('warehouse-stocks/release', [WarehouseStockController::class, 'release']);
    });

    // Stock Transfers - view access
    Route::middleware('permission:view inventory|manage inventory|view warehouses|manage transfers')->group(function () {
        Route::get('stock-transfers', [StockTransferController::class, 'index']);
        Route::get('stock-transfers/analytics', [StockTransferController::class, 'analytics']);
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);
        Route::get('stock-transfers/{stockTransfer}/items', [StockTransferController::class, 'items']);
        Route::get('warehouses/{warehouse}/transfers', [StockTransferController::class, 'byWarehouse']);
    });

    // Stock Transfers - management access
    Route::middleware('permission:manage transfers|manage inventory')->group(function () {
        Route::post('stock-transfers', [StockTransferController::class, 'store']);
        Route::put('stock-transfers/{stockTransfer}', [StockTransferController::class, 'update']);
        Route::delete('stock-transfers/{stockTransfer}', [StockTransferController::class, 'destroy']);
    });

    // Stock Transfers - approval and processing
    Route::middleware('permission:approve transfers|manage transfers')->group(function () {
        Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve']);
        Route::post('stock-transfers/{stockTransfer}/ship', [StockTransferController::class, 'ship']);
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive']);
        Route::post('stock-transfers/{stockTransfer}/reject', [StockTransferController::class, 'reject']);
    });

    // Sales Management Routes

    // Customer Management - view access for sales and customer service staff
    Route::middleware('permission:view customers|manage customers|process sales')->group(function () {
        Route::get('customers', [CustomerController::class, 'index']);
        Route::get('customers/search', [CustomerController::class, 'search']);
        Route::get('customers/analytics', [CustomerController::class, 'analytics']);
        Route::get('customers/{customer}', [CustomerController::class, 'show']);
        Route::get('customers/{customer}/purchase-history', [CustomerController::class, 'purchaseHistory']);
    });

    // Customer Management - create and edit access
    Route::middleware('permission:manage customers|process sales')->group(function () {
        Route::post('customers', [CustomerController::class, 'store']);
        Route::put('customers/{customer}', [CustomerController::class, 'update']);
        Route::patch('customers/{customer}/loyalty-points', [CustomerController::class, 'updateLoyaltyPoints']);
    });

    // Customer Management - delete access (restricted)
    Route::middleware('permission:manage customers')->group(function () {
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
    });

    // Payment Methods - view access for sales staff
    Route::middleware('permission:process sales|manage sales|view payment methods')->group(function () {
        Route::get('payment-methods', [PaymentMethodController::class, 'index']);
        Route::get('payment-methods/active', [PaymentMethodController::class, 'getActive']);
        Route::get('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'show']);
    });

    // Payment Methods - management access
    Route::middleware('permission:manage payment methods')->group(function () {
        Route::post('payment-methods', [PaymentMethodController::class, 'store']);
        Route::put('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update']);
        Route::delete('payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy']);
        Route::patch('payment-methods/{paymentMethod}/toggle-status', [PaymentMethodController::class, 'toggleStatus']);
    });

    // Cash Sessions - view access for sales staff and managers
    Route::middleware('permission:process sales|manage sales|view cash sessions')->group(function () {
        Route::get('cash-sessions', [CashSessionController::class, 'index']);
        Route::get('cash-sessions/active', [CashSessionController::class, 'getActive']);
        Route::get('cash-sessions/summary', [CashSessionController::class, 'getSummary']);
        Route::get('cash-sessions/{cashSession}', [CashSessionController::class, 'show']);
    });

    // Cash Sessions - open and close access
    Route::middleware('permission:process sales|manage cash')->group(function () {
        Route::post('cash-sessions', [CashSessionController::class, 'store']);
        Route::post('cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);
    });

    // Sales/POS - view access for sales staff and managers
    Route::middleware('permission:view sales|process sales|manage sales')->group(function () {
        Route::get('sales', [SaleController::class, 'index']);
        Route::get('sales/daily-summary', [SaleController::class, 'getDailySummary']);
        Route::get('sales/search-products', [SaleController::class, 'searchProducts']);
        Route::get('sales/{sale}', [SaleController::class, 'show']);
    });

    // Sales/POS - processing access
    Route::middleware('permission:process sales')->group(function () {
        Route::post('sales', [SaleController::class, 'store']);
    });

    // Sales/POS - void access (restricted)
    Route::middleware('permission:void sales|manage sales')->group(function () {
        Route::post('sales/{sale}/void', [SaleController::class, 'void']);
    });

    // Advanced Reporting & Analytics Routes

    // Dashboard Analytics - accessible to managers and above
    Route::middleware('permission:view reports|manage reports|view dashboard')->group(function () {
        Route::get('reports/dashboard', [ReportController::class, 'dashboard']);
    });

    // Sales Analytics - accessible to sales managers and above
    Route::middleware('permission:view reports|manage reports|view sales analytics')->group(function () {
        Route::get('reports/sales-analytics', [ReportController::class, 'salesAnalytics']);
    });

    // Inventory Analytics - accessible to inventory managers and above
    Route::middleware('permission:view reports|manage reports|view inventory analytics')->group(function () {
        Route::get('reports/inventory-analytics', [ReportController::class, 'inventoryAnalytics']);
    });

    // Purchasing Analytics - accessible to purchasing managers and above
    Route::middleware('permission:view reports|manage reports|view purchasing analytics')->group(function () {
        Route::get('reports/purchasing-analytics', [ReportController::class, 'purchasingAnalytics']);
    });

    // Financial Reports - accessible to financial managers and above
    Route::middleware('permission:view reports|manage reports|view financial reports')->group(function () {
        Route::get('reports/financial-reports', [ReportController::class, 'financialReports']);
    });

    // Legacy user route for backward compatibility
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
