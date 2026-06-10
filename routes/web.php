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
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\GoodsReceivedNoteController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ExpenseController;

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

// ── PAYMENT CALLBACKS (no auth — called by external providers) ───────────────
Route::post('/mpesa/callback', [POSController::class, 'mpesaCallback'])->name('mpesa.callback');

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

    // ── Categories (per-action RBAC + image upload) ───────────
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/',                         [CategoryController::class, 'index'])->name('index')->middleware('permission:view categories');
        Route::get('/create',                   [CategoryController::class, 'create'])->name('create')->middleware('permission:create categories');
        Route::post('/',                        [CategoryController::class, 'store'])->name('store')->middleware('permission:create categories');
        Route::get('/{category}',               [CategoryController::class, 'show'])->name('show')->middleware('permission:view categories');
        Route::get('/{category}/edit',          [CategoryController::class, 'edit'])->name('edit')->middleware('permission:edit categories');
        Route::put('/{category}',               [CategoryController::class, 'update'])->name('update')->middleware('permission:edit categories');
        Route::patch('/{category}',             [CategoryController::class, 'update'])->middleware('permission:edit categories');
        Route::delete('/{category}',            [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:delete categories');
        Route::post('/{category}/image',        [CategoryController::class, 'uploadImage'])->name('image')->middleware('permission:edit categories');
    });

    // ── Brands (per-action RBAC) ──────────────────────────────
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/',                         [BrandController::class, 'index'])->name('index')->middleware('permission:view categories');
        Route::get('/create',                   [BrandController::class, 'create'])->name('create')->middleware('permission:create categories');
        Route::post('/',                        [BrandController::class, 'store'])->name('store')->middleware('permission:create categories');
        Route::get('/{brand}/edit',             [BrandController::class, 'edit'])->name('edit')->middleware('permission:edit categories');
        Route::put('/{brand}',                  [BrandController::class, 'update'])->name('update')->middleware('permission:edit categories');
        Route::patch('/{brand}',                [BrandController::class, 'update'])->middleware('permission:edit categories');
        Route::delete('/{brand}',               [BrandController::class, 'destroy'])->name('destroy')->middleware('permission:delete categories');
    });

    // ── Units ─────────────────────────────────────────────────
    Route::resource('units', UnitController::class)->except(['show']);

    // ── Warehouses + transfers + reports (per-action RBAC — Hard Rule §6) ─────
    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        // Intra-branch transfers: register BEFORE /{warehouse} to avoid conflict
        Route::get('/transfers',                      [WarehouseController::class, 'indexTransfers'])->name('transfers.index')->middleware('permission:view warehouses');
        Route::get('/transfers/create',               [WarehouseController::class, 'createTransfer'])->name('transfers.create')->middleware('permission:create warehouses');
        Route::post('/transfers',                     [WarehouseController::class, 'storeTransfer'])->name('transfers.store')->middleware('permission:create warehouses');
        Route::post('/transfers/{transfer}/approve',  [WarehouseController::class, 'approveTransfer'])->name('transfers.approve')->middleware('permission:edit warehouses');
        Route::post('/transfers/{transfer}/dispatch', [WarehouseController::class, 'dispatchTransfer'])->name('transfers.dispatch')->middleware('permission:edit warehouses');
        Route::post('/transfers/{transfer}/receive',  [WarehouseController::class, 'receiveTransfer'])->name('transfers.receive')->middleware('permission:edit warehouses');

        // Warehouse CRUD
        Route::get('/',                      [WarehouseController::class, 'index'])->name('index')->middleware('permission:view warehouses');
        Route::get('/create',                [WarehouseController::class, 'create'])->name('create')->middleware('permission:create warehouses');
        Route::post('/',                     [WarehouseController::class, 'store'])->name('store')->middleware(['permission:create warehouses', 'check.warehouse.limit']);
        Route::get('/{warehouse}',           [WarehouseController::class, 'show'])->name('show')->middleware('permission:view warehouses');
        Route::get('/{warehouse}/edit',      [WarehouseController::class, 'edit'])->name('edit')->middleware('permission:edit warehouses');
        Route::put('/{warehouse}',           [WarehouseController::class, 'update'])->name('update')->middleware('permission:edit warehouses');
        Route::patch('/{warehouse}',         [WarehouseController::class, 'update'])->middleware('permission:edit warehouses');
        Route::delete('/{warehouse}',        [WarehouseController::class, 'destroy'])->name('destroy')->middleware('permission:delete warehouses');

        // Stock reports per warehouse
        Route::get('/{warehouse}/stock',      [WarehouseController::class, 'stockReport'])->name('stock')->middleware('permission:view warehouses');
        Route::get('/{warehouse}/movements',  [WarehouseController::class, 'movementsReport'])->name('movements')->middleware('permission:view warehouses');
        Route::get('/{warehouse}/valuation',  [WarehouseController::class, 'valuationReport'])->name('valuation')->middleware('permission:view warehouses');

        // Bin locations
        Route::post('/{warehouse}/locations',              [WarehouseController::class, 'storeLocation'])->name('locations.store')->middleware('permission:edit warehouses');
        Route::delete('/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->name('locations.destroy')->middleware('permission:delete warehouses');
    });

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
        Route::post('/adjust',     [InventoryController::class, 'storeAdjustment'])->name('adjust.store')->middleware('permission:inventory.adjust');

        // Alias: /inventory/adjustment (used in some blade links)
        Route::get('/adjustment',  [InventoryController::class, 'adjustment'])->name('adjustment');
        Route::post('/adjustment', [InventoryController::class, 'storeAdjustment'])->name('adjustment.store')->middleware('permission:inventory.adjust');

        // Transaction log
        Route::get('/transactions',      [InventoryController::class, 'transactions'])->name('transactions');
        Route::get('/transactions/{id}', [InventoryController::class, 'showTransaction'])->name('transactions.show');

        // Stock valuation
        Route::get('/valuation',   [InventoryController::class, 'valuation'])->name('valuation');

        // Low stock list
        Route::get('/low-stock',   [InventoryController::class, 'lowStock'])->name('low-stock');

        // AJAX: get current stock qty for product + warehouse
        Route::get('/stock-level', [InventoryController::class, 'getStockLevel'])->name('stock-level');

        // §5.5 InventoryService-powered routes (RBAC-protected, Hard Rule §6)
        Route::get('/out-of-stock',            [InventoryController::class, 'outOfStock'])->name('out-of-stock')->middleware('permission:inventory.view');
        Route::get('/product/{productId}',     [InventoryController::class, 'productStock'])->name('product-stock')->middleware('permission:inventory.view');
        Route::get('/movements/{productId}',   [InventoryController::class, 'movements'])->name('movements')->middleware('permission:inventory.view');
        // Note: no PUT/PATCH for movements — append-only (CLAUDE.md Hard Rule §4)

        // Single transaction detail (old route pattern kept)
        Route::get('/{transaction}', [InventoryController::class, 'show'])->name('show');
    });

    // ── Stock Transfers ───────────────────────────────────────
    Route::resource('transfers', StockTransferController::class)->except(['edit', 'update']);
    Route::post('transfers/{transfer}/approve',  [StockTransferController::class, 'approve'])->name('transfers.approve');
    Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('transfers.dispatch');
    Route::post('transfers/{transfer}/receive',  [StockTransferController::class, 'receive'])->name('transfers.receive');

    // ── Suppliers ─────────────────────────────────────────────
    Route::prefix('suppliers')->name('suppliers.')->group(function () {
        Route::get('/',                          [SupplierController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::get('/create',                    [SupplierController::class, 'create'])->name('create')->middleware('permission:purchases.create');
        Route::post('/',                         [SupplierController::class, 'store'])->name('store')->middleware('permission:purchases.create');
        // Sub-resource routes registered BEFORE /{supplier} to avoid conflict
        Route::get('/{supplier}/history',        [SupplierController::class, 'history'])->name('history')->middleware('permission:purchases.view');
        Route::get('/{supplier}/aging',          [SupplierController::class, 'aging'])->name('aging')->middleware('permission:purchases.view');
        Route::get('/{supplier}',                [SupplierController::class, 'show'])->name('show')->middleware('permission:purchases.view');
        Route::get('/{supplier}/edit',           [SupplierController::class, 'edit'])->name('edit')->middleware('permission:purchases.create');
        Route::put('/{supplier}',                [SupplierController::class, 'update'])->name('update')->middleware('permission:purchases.create');
        Route::patch('/{supplier}',              [SupplierController::class, 'update'])->middleware('permission:purchases.create');
        Route::delete('/{supplier}',             [SupplierController::class, 'destroy'])->name('destroy')->middleware('permission:purchases.manage');
    });

    // ── Purchase Orders ───────────────────────────────────────
    Route::prefix('purchases')->name('purchases.')->group(function () {
        Route::get('/',                      [PurchaseOrderController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::get('/create',                [PurchaseOrderController::class, 'create'])->name('create')->middleware('permission:purchases.create');
        Route::post('/',                     [PurchaseOrderController::class, 'store'])->name('store')->middleware('permission:purchases.create');
        Route::get('/{purchase}',            [PurchaseOrderController::class, 'show'])->name('show')->middleware('permission:purchases.view');
        Route::get('/{purchase}/edit',       [PurchaseOrderController::class, 'edit'])->name('edit')->middleware('permission:purchases.create');
        Route::put('/{purchase}',            [PurchaseOrderController::class, 'update'])->name('update')->middleware('permission:purchases.create');
        Route::patch('/{purchase}',          [PurchaseOrderController::class, 'update'])->middleware('permission:purchases.create');
        Route::delete('/{purchase}',         [PurchaseOrderController::class, 'destroy'])->name('destroy')->middleware('permission:purchases.manage');
        Route::post('/{purchase}/submit',    [PurchaseOrderController::class, 'submit'])->name('submit')->middleware('permission:purchases.create');
        Route::post('/{purchase}/approve',   [PurchaseOrderController::class, 'approve'])->name('approve')->middleware('permission:purchases.manage');
        Route::post('/{purchase}/receive',   [PurchaseOrderController::class, 'receive'])->name('receive')->middleware('permission:purchases.receive');
        Route::get('/{purchase}/pdf',        [PurchaseOrderController::class, 'pdf'])->name('pdf')->middleware('permission:purchases.view');
    });

    // ── Purchase Requisitions ─────────────────────────────────
    Route::prefix('requisitions')->name('requisitions.')->group(function () {
        Route::get('/',                      [RequisitionController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::get('/create',                [RequisitionController::class, 'create'])->name('create')->middleware('permission:purchases.view');
        Route::post('/',                     [RequisitionController::class, 'store'])->name('store')->middleware('permission:purchases.view');
        Route::get('/{requisition}',         [RequisitionController::class, 'show'])->name('show')->middleware('permission:purchases.view');
        Route::post('/{requisition}/submit', [RequisitionController::class, 'submit'])->name('submit')->middleware('permission:purchases.view');
        Route::post('/{requisition}/approve',[RequisitionController::class, 'approve'])->name('approve')->middleware('permission:purchases.manage');
        Route::post('/{requisition}/reject', [RequisitionController::class, 'reject'])->name('reject')->middleware('permission:purchases.manage');
        Route::post('/{requisition}/revise', [RequisitionController::class, 'revise'])->name('revise')->middleware('permission:purchases.manage');
    });

    // ── Goods Received Notes ──────────────────────────────────
    Route::prefix('grn')->name('grn.')->group(function () {
        Route::get('/',              [GoodsReceivedNoteController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::get('/create',        [GoodsReceivedNoteController::class, 'create'])->name('create')->middleware('permission:purchases.receive');
        Route::post('/',             [GoodsReceivedNoteController::class, 'store'])->name('store')->middleware('permission:purchases.receive');
        Route::get('/{grn}',         [GoodsReceivedNoteController::class, 'show'])->name('show')->middleware('permission:purchases.view');
        Route::post('/{grn}/confirm',[GoodsReceivedNoteController::class, 'confirm'])->name('confirm')->middleware('permission:purchases.receive');
    });

    // ── Supplier Invoices ─────────────────────────────────────
    Route::prefix('invoices')->name('invoices.')->group(function () {
        Route::get('/',              [SupplierInvoiceController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::get('/create',        [SupplierInvoiceController::class, 'create'])->name('create')->middleware('permission:purchases.create');
        Route::post('/',             [SupplierInvoiceController::class, 'store'])->name('store')->middleware('permission:purchases.create');
        Route::get('/{invoice}',     [SupplierInvoiceController::class, 'show'])->name('show')->middleware('permission:purchases.view');
        Route::post('/{invoice}/match',[SupplierInvoiceController::class, 'match'])->name('match')->middleware('permission:purchases.manage');
        Route::post('/{invoice}/pay',[SupplierInvoiceController::class, 'pay'])->name('pay')->middleware('permission:purchases.manage');
    });

    // ── Purchase Returns ──────────────────────────────────────
    Route::prefix('purchase-returns')->name('purchase-returns.')->group(function () {
        Route::get('/',                      [PurchaseReturnController::class, 'index'])->name('index')->middleware('permission:purchases.view');
        Route::post('/',                     [PurchaseReturnController::class, 'store'])->name('store')->middleware('permission:purchases.manage');
        Route::get('/{purchaseReturn}',      [PurchaseReturnController::class, 'show'])->name('show')->middleware('permission:purchases.view');
    });

    // ── Customers ─────────────────────────────────────────────
    Route::prefix('customers')->name('customers.')->group(function () {
        // Static routes BEFORE /{customer} parametric route (Hard Rule §6)
        Route::get('/',            [CustomerController::class, 'index'])->name('index')->middleware('permission:sales.view');
        Route::get('/segments',    [CustomerController::class, 'segments'])->name('segments')->middleware('permission:sales.view');
        Route::post('/',           [CustomerController::class, 'store'])->name('store')->middleware('permission:sales.create');

        // Sub-resource routes registered BEFORE /{customer} catch-all
        Route::get('/{customer}/history',              [CustomerController::class, 'history'])->name('history')->middleware('permission:sales.view');
        Route::get('/{customer}/balance',              [CustomerController::class, 'balance'])->name('balance')->middleware('permission:sales.view');
        Route::post('/{customer}/tags',                [CustomerController::class, 'assignTags'])->name('tags.assign')->middleware('permission:sales.create');
        Route::delete('/{customer}/tags/{tag}',        [CustomerController::class, 'removeTag'])->name('tags.remove')->middleware('permission:sales.create');

        // CRUD
        Route::get('/{customer}',    [CustomerController::class, 'show'])->name('show')->middleware('permission:sales.view');
        Route::put('/{customer}',    [CustomerController::class, 'update'])->name('update')->middleware('permission:sales.create');
        Route::patch('/{customer}',  [CustomerController::class, 'update'])->middleware('permission:sales.create');
        Route::delete('/{customer}', [CustomerController::class, 'destroy'])->name('destroy')->middleware('permission:sales.manage');
    });

    // ── Sales (B2B orders) ────────────────────────────────────
    Route::resource('sales',     SalesOrderController::class);
    Route::post('sales/{sale}/confirm',  [SalesOrderController::class, 'confirm'])->name('sales.confirm');
    Route::post('sales/{sale}/dispatch', [SalesOrderController::class, 'dispatch'])->name('sales.dispatch');
    Route::post('sales/{sale}/deliver',  [SalesOrderController::class, 'deliver'])->name('sales.deliver');
    Route::post('sales/{sale}/payment',  [SalesOrderController::class, 'recordPayment'])->name('sales.payment');
    Route::post('sales/{sale}/cancel',   [SalesOrderController::class, 'cancel'])->name('sales.cancel');
    Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'cancel'])->name('transfers.cancel');

    // ── POS ───────────────────────────────────────────────────
    Route::prefix('pos')->name('pos.')->group(function () {
        // Session management (terminal limit check on open only — Hard Rule §7)
        Route::get('/session',        [POSController::class, 'session'])->name('session')->middleware('permission:sales.create');
        Route::post('/session/open',  [POSController::class, 'openSession'])->name('session.open')->middleware(['permission:sales.create', 'check.pos.terminal']);
        Route::post('/session/close', [POSController::class, 'closeSession'])->name('session.close')->middleware('permission:sales.create');

        // Product search
        Route::get('/products/search', [POSController::class, 'searchProducts'])->name('products.search')->middleware('permission:sales.view');

        // Sale creation
        Route::post('/sales',         [POSController::class, 'store'])->name('sales.store')->middleware('permission:sales.create');

        // Sale management (list/detail/void/receipts/returns)
        Route::get('/sales',                          [SaleController::class, 'index'])->name('sales.index')->middleware('permission:sales.view');
        Route::get('/sales/{sale}',                   [SaleController::class, 'show'])->name('sales.show')->middleware('permission:sales.view');
        Route::post('/sales/{sale}/void',             [SaleController::class, 'void'])->name('sales.void')->middleware('permission:sales.manage');
        Route::get('/sales/{sale}/receipt/thermal',   [SaleController::class, 'receiptThermal'])->name('sales.receipt.thermal')->middleware('permission:sales.view');
        Route::get('/sales/{sale}/receipt/a4',        [SaleController::class, 'receiptA4'])->name('sales.receipt.a4')->middleware('permission:sales.view');
        Route::get('/sales/{sale}/receipt/email',     [SaleController::class, 'receiptEmail'])->name('sales.receipt.email')->middleware('permission:sales.view');
        Route::post('/sales/{sale}/return',           [SaleController::class, 'storeReturn'])->name('sales.return')->middleware('permission:sales.create');

        // Loyalty points redemption
        Route::post('/loyalty/redeem', [CustomerController::class, 'redeemLoyalty'])->name('loyalty.redeem')->middleware('permission:sales.create');
    });

    // ── Expenses ─────────────────────────────────────────────
    Route::prefix('expenses')->name('expenses.')->group(function () {
        // Static routes BEFORE /{expense} to avoid route conflicts
        Route::get('/',         [ExpenseController::class, 'index'])->name('index')->middleware('permission:expenses.view');
        Route::post('/',        [ExpenseController::class, 'store'])->name('store')->middleware('permission:expenses.create');
        Route::get('/summary',  [ExpenseController::class, 'summary'])->name('summary')->middleware('permission:expenses.view');
        Route::get('/budgets',  [ExpenseController::class, 'indexBudgets'])->name('budgets.index')->middleware('permission:expenses.view');
        Route::post('/budgets', [ExpenseController::class, 'storeBudget'])->name('budgets.store')->middleware('permission:expenses.manage');

        // Per-expense routes
        Route::get('/{expense}',             [ExpenseController::class, 'show'])->name('show')->middleware('permission:expenses.view');
        Route::put('/{expense}',             [ExpenseController::class, 'update'])->name('update')->middleware('permission:expenses.create');
        Route::patch('/{expense}',           [ExpenseController::class, 'update'])->middleware('permission:expenses.create');
        Route::post('/{expense}/submit',     [ExpenseController::class, 'submit'])->name('submit')->middleware('permission:expenses.create');
        Route::post('/{expense}/approve',    [ExpenseController::class, 'approve'])->name('approve')->middleware('permission:expenses.manage');
        Route::post('/{expense}/reject',     [ExpenseController::class, 'reject'])->name('reject')->middleware('permission:expenses.manage');
        Route::post('/{expense}/receipt',    [ExpenseController::class, 'uploadReceipt'])->name('receipt')->middleware('permission:expenses.create');
    });

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