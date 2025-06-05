<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\StockMovementController;
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

    // Legacy user route for backward compatibility
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
