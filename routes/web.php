<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockTransferController;

// ── PUBLIC ────────────────────────────────────────────────────────────────────
Route::get('/', fn() => view('welcome'))->name('home');

// ── AUTHENTICATION ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->name('login.post');
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── ALL PROTECTED ROUTES ──────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // ── Dashboard ─────────────────────────────────────────────
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('require.2fa')
        ->name('dashboard');

    // ── Products (individual routes for per-action RBAC + plan limit) ─────────
    Route::prefix('products')->name('products.')->group(function () {
        // view
        Route::get('/',                    [ProductController::class, 'index'])->name('index')->middleware('permission:view products');
        Route::get('/search',              [ProductController::class, 'search'])->name('search')->middleware('permission:view products');
        Route::get('/export',              [ProductController::class, 'export'])->name('export')->middleware('permission:view products');
        Route::get('/import/template',     [ProductController::class, 'importTemplate'])->name('import.template')->middleware('permission:view products');
        Route::get('/create',              [ProductController::class, 'create'])->name('create')->middleware('permission:create products');
        // create + plan limit (Hard Rule §7)
        Route::post('/',                   [ProductController::class, 'store'])->name('store')->middleware(['permission:create products', 'check.product.limit']);
        Route::post('/import',             [ProductController::class, 'import'])->name('import')->middleware(['permission:create products', 'check.product.limit']);
        // show (after static prefixes to avoid swallowing /create, /search, etc.)
        Route::get('/{product}',           [ProductController::class, 'show'])->name('show')->middleware('permission:view products');
        // edit
        Route::get('/{product}/edit',      [ProductController::class, 'edit'])->name('edit')->middleware('permission:edit products');
        Route::put('/{product}',           [ProductController::class, 'update'])->name('update')->middleware('permission:edit products');
        Route::patch('/{product}',         [ProductController::class, 'update'])->middleware('permission:edit products');
        Route::post('/{product}/images',   [ProductController::class, 'uploadImage'])->name('images.store')->middleware('permission:edit products');
        Route::delete('/{product}/images/{image}', [ProductController::class, 'deleteImage'])->name('images.destroy')->middleware('permission:edit products');
        // delete
        Route::delete('/{product}',        [ProductController::class, 'destroy'])->name('destroy')->middleware('permission:delete products');
    });

    // ── Other Catalogue ───────────────────────────────────────
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('brands',     BrandController::class)->except(['show']);
    Route::resource('units',      UnitController::class)->except(['show']);

    // ── Warehouses + bin locations ────────────────────────────
    Route::resource('warehouses', WarehouseController::class);
    Route::post('warehouses/{warehouse}/locations',              [WarehouseController::class, 'storeLocation'])->name('warehouses.locations.store');
    Route::delete('warehouses/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->name('warehouses.locations.destroy');

    // ── Inventory ─────────────────────────────────────────────
    Route::prefix('inventory')->name('inventory.')->group(function () {

        // Main stock levels page
        Route::get('/',            [InventoryController::class, 'index'])->name('index');

        // /inventory/stock  — alias used by sidebar & old links
        Route::get('/stock',       [InventoryController::class, 'index'])->name('stock');

        // /inventory/stock-on-hand — legacy alias kept for backward compat
        Route::get('/stock-on-hand', [InventoryController::class, 'stockOnHand'])->name('stock-on-hand');

        // Stock adjustment
        Route::get('/adjust',      [InventoryController::class, 'adjustment'])->name('adjust');
        Route::post('/adjust',     [InventoryController::class, 'storeAdjustment'])->name('adjust.store');

        // Alias: /inventory/adjustment (used in some blade links)
        Route::get('/adjustment',  [InventoryController::class, 'adjustment'])->name('adjustment');
        Route::post('/adjustment', [InventoryController::class, 'storeAdjustment'])->name('adjustment.store');

        // Transaction log
        Route::get('/transactions',      [InventoryController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/{id}', [InventoryController::class, 'showTransaction'])->name('transactions.show');

        // Stock valuation
        Route::get('/valuation',   [InventoryController::class, 'valuation'])->name('valuation');

        // Low stock list
        Route::get('/low-stock',   [InventoryController::class, 'lowStock'])->name('low-stock');

        // AJAX: get current stock qty for product + warehouse
        Route::get('/stock-level', [InventoryController::class, 'getStockLevel'])->name('stock-level');

        // Single transaction detail (old route pattern kept)
        Route::get('/{transaction}', [InventoryController::class, 'show'])->name('show');
    });

    // ── Stock Transfers ───────────────────────────────────────
    Route::resource('transfers', StockTransferController::class)->except(['edit', 'update']);
    Route::post('transfers/{transfer}/approve',  [StockTransferController::class, 'approve'])->name('transfers.approve');
    Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('transfers.dispatch');
    Route::post('transfers/{transfer}/receive',  [StockTransferController::class, 'receive'])->name('transfers.receive');

    // ── Purchasing ────────────────────────────────────────────
    Route::resource('suppliers', SupplierController::class);
    Route::resource('purchases', PurchaseOrderController::class);
    Route::post('purchases/{purchase}/submit',  [PurchaseOrderController::class, 'submit'])->name('purchases.submit');
    Route::post('purchases/{purchase}/approve', [PurchaseOrderController::class, 'approve'])->name('purchases.approve');
    Route::post('purchases/{purchase}/receive', [PurchaseOrderController::class, 'receive'])->name('purchases.receive');

    // ── Sales ─────────────────────────────────────────────────
    Route::resource('customers', CustomerController::class);
    Route::resource('sales',     SalesOrderController::class);
    Route::post('sales/{sale}/confirm',  [SalesOrderController::class, 'confirm'])->name('sales.confirm');
    Route::post('sales/{sale}/dispatch', [SalesOrderController::class, 'dispatch'])->name('sales.dispatch');
    Route::post('sales/{sale}/deliver',  [SalesOrderController::class, 'deliver'])->name('sales.deliver');
    Route::post('sales/{sale}/payment',  [SalesOrderController::class, 'recordPayment'])->name('sales.payment');
    Route::post('sales/{sale}/cancel',   [SalesOrderController::class, 'cancel'])->name('sales.cancel');
    Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

    // ── Reports ───────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/',          [ReportController::class, 'index'])->name('index');
        Route::get('/stock',     [ReportController::class, 'stockOnHand'])->name('stock');
        Route::get('/low-stock', [ReportController::class, 'lowStock'])->name('low-stock');
        Route::get('/expiry',    [ReportController::class, 'expiry'])->name('expiry');
        Route::get('/valuation', [ReportController::class, 'valuation'])->name('valuation');
        Route::get('/purchases', [ReportController::class, 'purchases'])->name('purchases');
        Route::get('/sales',     [ReportController::class, 'sales'])->name('sales');
        Route::get('/movements', [ReportController::class, 'movements'])->name('movements');
    });

    // ── Profile ───────────────────────────────────────────────
    Route::get('/profile', [UserController::class, 'profile'])->name('profile');
    Route::put('/profile', [UserController::class, 'updateProfile'])->name('profile.update');

    // ── Users (Super Admin only) ──────────────────────────────
    Route::middleware('role:Super Admin')->resource('users', UserController::class);
});