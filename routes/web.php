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
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\GoodsReceivedNoteController;
use App\Http\Controllers\SupplierInvoiceController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\POSController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FinancialController;
use App\Http\Controllers\InventoryAuditController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ShiftController;

// ── PUBLIC ────────────────────────────────────────────────────────────────────
Route::get('/', fn() => view('welcome'))->name('home');

// ── AUTHENTICATION ────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',     [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login',    [AuthController::class, 'login'])->name('login.post');
    Route::get('/register',  [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.post');

    // Forgot password (MVP: generates temp password displayed on screen)
    Route::get('/forgot-password',  [ForgotPasswordController::class, 'showForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('password.email');
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

    // Dismiss the "must change password" banner (sets a session flag, not permanent)
    Route::post('/banner/dismiss-password-notice', function () {
        session(['pw_notice_dismissed' => true]);
        return back();
    })->name('banner.dismiss.password');

    // ── Products (individual routes for per-action RBAC + plan limit) ─────────
    Route::prefix('products')->name('products.')->group(function () {
        // view
        Route::get('/',                    [ProductController::class, 'index'])->name('index')->middleware('permission:products.view');
        Route::get('/search',              [ProductController::class, 'search'])->name('search')->middleware('permission:products.view');
        Route::get('/export',              [ProductController::class, 'export'])->name('export')->middleware('permission:products.view');
        Route::get('/import/template',     [ProductController::class, 'importTemplate'])->name('import.template')->middleware('permission:products.view');
        Route::get('/create',              [ProductController::class, 'create'])->name('create')->middleware('permission:products.manage');
        // create + plan limit (Hard Rule §7)
        Route::post('/',                   [ProductController::class, 'store'])->name('store')->middleware(['permission:products.manage', 'check.product.limit']);
        Route::post('/import',             [ProductController::class, 'import'])->name('import')->middleware(['permission:products.manage', 'check.product.limit']);
        // show (after static prefixes to avoid swallowing /create, /search, etc.)
        Route::get('/{product}',           [ProductController::class, 'show'])->name('show')->middleware('permission:products.view');
        // edit
        Route::get('/{product}/edit',      [ProductController::class, 'edit'])->name('edit')->middleware('permission:products.manage');
        Route::put('/{product}',           [ProductController::class, 'update'])->name('update')->middleware('permission:products.manage');
        Route::patch('/{product}',         [ProductController::class, 'update'])->middleware('permission:products.manage');
        Route::post('/{product}/images',   [ProductController::class, 'uploadImage'])->name('images.store')->middleware('permission:products.manage');
        Route::delete('/{product}/images/{image}', [ProductController::class, 'deleteImage'])->name('images.destroy')->middleware('permission:products.manage');
        // delete
        Route::delete('/{product}',        [ProductController::class, 'destroy'])->name('destroy')->middleware('permission:products.manage');
    });

    // ── Categories (per-action RBAC + image upload) ───────────
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/',                         [CategoryController::class, 'index'])->name('index')->middleware('permission:products.manage');
        Route::get('/create',                   [CategoryController::class, 'create'])->name('create')->middleware('permission:products.manage');
        Route::post('/',                        [CategoryController::class, 'store'])->name('store')->middleware('permission:products.manage');
        Route::get('/{category}',               [CategoryController::class, 'show'])->name('show')->middleware('permission:products.manage');
        Route::get('/{category}/edit',          [CategoryController::class, 'edit'])->name('edit')->middleware('permission:products.manage');
        Route::put('/{category}',               [CategoryController::class, 'update'])->name('update')->middleware('permission:products.manage');
        Route::patch('/{category}',             [CategoryController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/{category}',            [CategoryController::class, 'destroy'])->name('destroy')->middleware('permission:products.manage');
        Route::post('/{category}/image',        [CategoryController::class, 'uploadImage'])->name('image')->middleware('permission:products.manage');
    });

    // ── Brands (per-action RBAC) ──────────────────────────────
    Route::prefix('brands')->name('brands.')->group(function () {
        Route::get('/',                         [BrandController::class, 'index'])->name('index')->middleware('permission:products.manage');
        Route::get('/create',                   [BrandController::class, 'create'])->name('create')->middleware('permission:products.manage');
        Route::post('/',                        [BrandController::class, 'store'])->name('store')->middleware('permission:products.manage');
        Route::get('/{brand}/edit',             [BrandController::class, 'edit'])->name('edit')->middleware('permission:products.manage');
        Route::put('/{brand}',                  [BrandController::class, 'update'])->name('update')->middleware('permission:products.manage');
        Route::patch('/{brand}',                [BrandController::class, 'update'])->middleware('permission:products.manage');
        Route::delete('/{brand}',               [BrandController::class, 'destroy'])->name('destroy')->middleware('permission:products.manage');
    });

    // ── Units ─────────────────────────────────────────────────
    Route::resource('units', UnitController::class)->except(['show'])->middleware('permission:products.manage');

    // ── Warehouses + transfers + reports (per-action RBAC — Hard Rule §6) ─────
    Route::prefix('warehouses')->name('warehouses.')->group(function () {
        // Intra-branch transfers: register BEFORE /{warehouse} to avoid conflict
        Route::get('/transfers',                      [WarehouseController::class, 'indexTransfers'])->name('transfers.index')->middleware('permission:inventory.audit');
        Route::get('/transfers/create',               [WarehouseController::class, 'createTransfer'])->name('transfers.create')->middleware('permission:inventory.adjust');
        Route::post('/transfers',                     [WarehouseController::class, 'storeTransfer'])->name('transfers.store')->middleware('permission:inventory.adjust');
        Route::post('/transfers/{transfer}/approve',  [WarehouseController::class, 'approveTransfer'])->name('transfers.approve')->middleware('permission:inventory.adjust');
        Route::post('/transfers/{transfer}/dispatch', [WarehouseController::class, 'dispatchTransfer'])->name('transfers.dispatch')->middleware('permission:inventory.adjust');
        Route::post('/transfers/{transfer}/receive',  [WarehouseController::class, 'receiveTransfer'])->name('transfers.receive')->middleware('permission:inventory.adjust');

        // Warehouse CRUD
        Route::get('/',                      [WarehouseController::class, 'index'])->name('index')->middleware('permission:inventory.audit');
        Route::get('/create',                [WarehouseController::class, 'create'])->name('create')->middleware('permission:inventory.adjust');
        Route::post('/',                     [WarehouseController::class, 'store'])->name('store')->middleware(['permission:inventory.adjust', 'check.warehouse.limit']);
        Route::get('/{warehouse}',           [WarehouseController::class, 'show'])->name('show')->middleware('permission:inventory.audit');
        Route::get('/{warehouse}/edit',      [WarehouseController::class, 'edit'])->name('edit')->middleware('permission:inventory.adjust');
        Route::put('/{warehouse}',           [WarehouseController::class, 'update'])->name('update')->middleware('permission:inventory.adjust');
        Route::patch('/{warehouse}',         [WarehouseController::class, 'update'])->middleware('permission:inventory.adjust');
        Route::delete('/{warehouse}',        [WarehouseController::class, 'destroy'])->name('destroy')->middleware('permission:inventory.adjust');

        // Stock reports per warehouse
        Route::get('/{warehouse}/stock',      [WarehouseController::class, 'stockReport'])->name('stock')->middleware('permission:inventory.audit');
        Route::get('/{warehouse}/movements',  [WarehouseController::class, 'movementsReport'])->name('movements')->middleware('permission:inventory.audit');
        Route::get('/{warehouse}/valuation',  [WarehouseController::class, 'valuationReport'])->name('valuation')->middleware('permission:inventory.audit');

        // Bin locations
        Route::post('/{warehouse}/locations',              [WarehouseController::class, 'storeLocation'])->name('locations.store')->middleware('permission:inventory.adjust');
        Route::delete('/{warehouse}/locations/{location}', [WarehouseController::class, 'destroyLocation'])->name('locations.destroy')->middleware('permission:inventory.adjust');
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
        Route::get('/adjust',      [InventoryController::class, 'adjustment'])->name('adjust')->middleware('permission:inventory.adjust');
        Route::post('/adjust',     [InventoryController::class, 'storeAdjustment'])->name('adjust.store')->middleware('permission:inventory.adjust');

        // Alias: /inventory/adjustment (used in some blade links)
        Route::get('/adjustment',  [InventoryController::class, 'adjustment'])->name('adjustment')->middleware('permission:inventory.adjust');
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

    // ── Inventory Audits (§5.14) ─────────────────────────────────────────────
    Route::prefix('audits')->name('audits.')->group(function () {
        Route::get('/',                        [InventoryAuditController::class, 'index'])->name('index')->middleware('permission:inventory.audit');
        Route::post('/',                       [InventoryAuditController::class, 'store'])->name('store')->middleware('permission:inventory.audit');
        Route::get('/{audit}',                 [InventoryAuditController::class, 'show'])->name('show')->middleware('permission:inventory.audit');
        Route::get('/{audit}/sheet',           [InventoryAuditController::class, 'sheet'])->name('sheet')->middleware('permission:inventory.audit_count');
        Route::post('/{audit}/counts',         [InventoryAuditController::class, 'counts'])->name('counts')->middleware('permission:inventory.audit_count');
        Route::get('/{audit}/variance',        [InventoryAuditController::class, 'variance'])->name('variance')->middleware('permission:inventory.audit');
        Route::post('/{audit}/post',           [InventoryAuditController::class, 'post'])->name('post')->middleware('permission:inventory.audit');
    });

    // ── Branch Stock Transfers (§5.13) ───────────────────────────────────────
    // View routes (no plan check required — users can see existing transfers)
    Route::get('transfers',                [StockTransferController::class, 'index'])->name('transfers.index')->middleware('permission:transfers.view');
    Route::get('transfers/create',         [StockTransferController::class, 'create'])->name('transfers.create')->middleware(['permission:transfers.create', 'check.transfer.feature']);
    Route::get('transfers/quick-create',   [StockTransferController::class, 'quickCreate'])->name('transfers.quick-create')->middleware(['permission:transfers.create', 'check.transfer.feature']);
    Route::get('transfers/{transfer}',     [StockTransferController::class, 'show'])->name('transfers.show')->middleware('permission:transfers.view');

    // Mutating routes require plan feature + permission (Hard Rules §6 & §7)
    Route::middleware(['permission:transfers.create', 'check.transfer.feature'])->group(function () {
        Route::post('transfers', [StockTransferController::class, 'store'])->name('transfers.store');
    });
    Route::post('transfers/{transfer}/approve',  [StockTransferController::class, 'approve'])->name('transfers.approve')->middleware('permission:transfers.approve');
    Route::post('transfers/{transfer}/reject',   [StockTransferController::class, 'reject'])->name('transfers.reject')->middleware('permission:transfers.approve');
    Route::post('transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch'])->name('transfers.dispatch')->middleware('permission:transfers.dispatch');
    Route::post('transfers/{transfer}/receive',  [StockTransferController::class, 'receive'])->name('transfers.receive')->middleware('permission:transfers.receive');
    Route::delete('transfers/{transfer}',        [StockTransferController::class, 'destroy'])->name('transfers.destroy')->middleware('permission:transfers.create');

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
        Route::get('/{purchase}/receive',    [PurchaseOrderController::class, 'receive'])->name('receive')->middleware('permission:purchases.receive');
        Route::post('/{purchase}/receive',   [PurchaseOrderController::class, 'storeReceipt'])->name('storeReceipt')->middleware('permission:purchases.receive');
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
        Route::post('/{requisition}/revise',    [RequisitionController::class, 'revise'])->name('revise')->middleware('permission:purchases.manage');
        Route::post('/{requisition}/resubmit', [RequisitionController::class, 'resubmit'])->name('resubmit')->middleware('permission:purchases.view');
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
    Route::post('transfers/{transfer}/cancel', [StockTransferController::class, 'destroy'])->name('transfers.cancel')->middleware('permission:transfers.create');

    // ── POS ───────────────────────────────────────────────────
    Route::prefix('pos')->name('pos.')->group(function () {
        // Terminal UI (CA-1)
        Route::get('/',               [POSController::class, 'terminal'])->name('terminal')->middleware('permission:sales.create');

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
        Route::get('/{expense}/export-pdf',  [ExpenseController::class, 'exportPdf'])->name('export-pdf')->middleware('permission:expenses.view');
        Route::get('/{expense}/export-excel',[ExpenseController::class, 'exportExcel'])->name('export-excel')->middleware('permission:expenses.view');
    });

    // ── Reports ───────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/',                     [ReportController::class, 'index'])->name('index');
            Route::get('/stock',                [ReportController::class, 'stockOnHand'])->name('stock');
            Route::get('/low-stock',            [ReportController::class, 'lowStock'])->name('low-stock');
            Route::get('/expiry',               [ReportController::class, 'expiry'])->name('expiry');
            Route::post('/expiry/batches/{batch}/flag-promotion', [ReportController::class, 'flagBatchForPromotion'])->name('expiry.flag-promotion');
            Route::get('/valuation',            [ReportController::class, 'valuation'])->name('valuation');
            Route::get('/purchases',            [ReportController::class, 'purchases'])->name('purchases');
            Route::get('/sales',                [ReportController::class, 'sales'])->name('sales');
            Route::get('/movements',            [ReportController::class, 'movements'])->name('movements');

            // §5.16 — 16 report types
            Route::get('/daily-sales',          [ReportController::class, 'dailySales'])->name('daily-sales');
            Route::get('/sales-trend',          [ReportController::class, 'salesTrend'])->name('sales-trend');
            Route::get('/product-performance',  [ReportController::class, 'productPerformance'])->name('product-performance');
            Route::get('/dead-stock',           [ReportController::class, 'deadStock'])->name('dead-stock');
            Route::get('/inventory-valuation',  [ReportController::class, 'inventoryValuation'])->name('inventory-valuation');
            Route::get('/purchase-summary',     [ReportController::class, 'purchaseSummary'])->name('purchase-summary');
            Route::get('/grn-vs-ordered',       [ReportController::class, 'grnVsOrdered'])->name('grn-vs-ordered');
            Route::get('/expense-breakdown',    [ReportController::class, 'expenseBreakdown'])->name('expense-breakdown');
            Route::get('/employee-performance', [ReportController::class, 'employeePerformance'])->name('employee-performance');
            Route::get('/audit-variance',       [ReportController::class, 'auditVariance'])->name('audit-variance');
            Route::get('/customer-history',     [ReportController::class, 'customerHistory'])->name('customer-history');
            Route::get('/supplier-aging',       [ReportController::class, 'supplierAging'])->name('supplier-aging');

            // Scheduled delivery (plan feature)
            Route::get('/schedules',  [ReportController::class, 'schedules'])->name('schedules.index');
            Route::post('/schedules', [ReportController::class, 'storeSchedule'])->name('schedules.store');
        });

        // §5.12 Financial Management (Hard Rule §6: permission middleware on every route)
        Route::middleware('permission:reports.financial')->group(function () {
            Route::get('/income-statement', [FinancialController::class, 'incomeStatement'])->name('financial.income-statement');
            Route::get('/cash-flow',        [FinancialController::class, 'cashFlow'])->name('financial.cash-flow');
            Route::get('/balance-sheet',    [FinancialController::class, 'balanceSheet'])->name('financial.balance-sheet');
            Route::get('/export/{report}/pdf',   [FinancialController::class, 'exportPdf'])->name('financial.export.pdf');
            Route::get('/export/{report}/excel', [FinancialController::class, 'exportExcel'])->name('financial.export.excel');
            // §5.16 P&L and cash-flow aliases
            Route::get('/pnl',       [ReportController::class, 'pnl'])->name('pnl');
            Route::get('/cash-flow-report', [ReportController::class, 'cashFlow'])->name('cash-flow-report');
        });

        // VAT report uses a separate, more restricted permission
        Route::middleware('permission:reports.vat')->group(function () {
            Route::get('/vat',         [FinancialController::class, 'vatReport'])->name('financial.vat');
            Route::get('/vat-report',  [ReportController::class, 'vat'])->name('vat-report');
        });
    });

    // ── Profile ───────────────────────────────────────────────
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');

    // ── Users (Super Admin only) ──────────────────────────────
    Route::middleware('role:Super Admin')->resource('users', UserController::class);

    // ── Barcodes & Scanners (§5.15) ───────────────────────────
    Route::prefix('products/{product}')->name('products.')->group(function () {
        Route::get('/barcode',         [BarcodeController::class, 'show'])->name('barcode')->middleware('permission:inventory.view');
        Route::post('/barcode/assign', [BarcodeController::class, 'assign'])->name('barcode.assign')->middleware('permission:inventory.adjust');
    });
    Route::post('/barcodes/bulk-print', [BarcodeController::class, 'bulkPrint'])->name('barcodes.bulk-print')->middleware('permission:inventory.view');
    Route::get('/pos/scan/{barcode}',   [BarcodeController::class, 'posScan'])->name('pos.scan')->middleware('permission:sales.create');
    Route::get('/grn/scan/{barcode}',   [BarcodeController::class, 'grnScan'])->name('grn.scan')->middleware('permission:purchases.receive');

    // ── Employees & Attendance (§5.18) ────────────────────────
    Route::middleware('permission:employees.view')->group(function () {
        Route::resource('employees', EmployeeController::class)->except(['create', 'store', 'edit', 'update', 'destroy']);
        Route::get('/employees/{employee}/performance', [EmployeeController::class, 'performance'])->name('employees.performance');
        Route::get('/employees/{employee}/schedule',   [EmployeeController::class, 'schedule'])->name('employees.schedule');
        Route::get('/attendance/today',                [AttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/attendance/{employee}/monthly',   [AttendanceController::class, 'monthlyReport'])->name('attendance.monthly');
        Route::get('/shifts',                          [ShiftController::class, 'index'])->name('shifts.index');
    });

    Route::middleware('permission:employees.create')->group(function () {
        Route::get('/employees/create',  [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employees',        [EmployeeController::class, 'store'])->name('employees.store');
        Route::post('/shifts',           [ShiftController::class, 'store'])->name('shifts.store');
        Route::post('/employees/{employee}/link-user', [EmployeeController::class, 'linkUser'])->name('employees.link-user');
        Route::post('/employees/{employee}/shifts',    [ShiftController::class, 'assignToEmployee'])->name('employees.shifts.assign');
    });

    Route::middleware('permission:employees.edit')->group(function () {
        Route::get('/employees/{employee}/edit',  [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}',       [EmployeeController::class, 'update'])->name('employees.update');
        Route::put('/shifts/{shift}',             [ShiftController::class, 'update'])->name('shifts.update');
    });

    Route::middleware('permission:employees.delete')->group(function () {
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });

    Route::middleware('permission:attendance.manage')->group(function () {
        Route::post('/attendance/{employee}/clock-in',  [AttendanceController::class, 'clockIn'])->name('attendance.clock-in');
        Route::post('/attendance/{employee}/clock-out', [AttendanceController::class, 'clockOut'])->name('attendance.clock-out');
    });
});