<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReportJob;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryAudit;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\ReportSchedule;
use App\Models\Sale;
use App\Models\SalesOrder;
use App\Models\StockBalance;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ReportController extends Controller
{
    private const QUEUE_THRESHOLD = 1000;

    // ── REPORTS OVERVIEW ─────────────────────────────────────────────────────

    public function index()
    {
        $summary = [
            'total_stock_value' => StockBalance::join('products', 'products.id', '=', 'stock_balances.product_id')
                ->selectRaw('SUM(stock_balances.quantity_available * products.cost_price) as total')
                ->value('total') ?? 0,
            'low_stock_count' => Product::active()->lowStock()->count(),
            'expiring_soon' => ProductBatch::expiringSoon(30)->count(),
            'pending_po_value' => PurchaseOrder::whereIn('status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'])
                ->sum('total_amount'),
            'pending_sales_value' => SalesOrder::whereIn('status', ['CONFIRMED', 'PROCESSING'])
                ->sum('total_amount'),
            'transactions_today' => Sale::where('status', 'completed')->whereDate('created_at', today())->count(),
        ];

        $warehouses = Warehouse::active()->get();

        return view('reports.index', compact('summary', 'warehouses'));
    }

    // ── 1. DAILY SALES ───────────────────────────────────────────────────────

    public function dailySales(Request $request): mixed
    {
        $date = $request->get('date', today()->toDateString());
        $branchId = $request->integer('branch_id') ?: null;
        $cashierId = $request->integer('cashier_id') ?: null;
        $tenantId = $this->tenantId();

        $base = fn () => DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereDate('created_at', $date)
            ->whereNull('deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($cashierId, fn ($q) => $q->where('cashier_id', $cashierId));

        $totals = $base()->selectRaw(
            'SUM(grand_total) as revenue, COUNT(*) as transactions, SUM(discount) as discounts, SUM(tax) as tax'
        )->first();

        $hourExpr = $this->exprHour('created_at');
        $byHour = $base()
            ->selectRaw("{$hourExpr} as hour, SUM(grand_total) as total, COUNT(*) as count")
            ->groupByRaw($hourExpr)
            ->orderBy('hour')
            ->get();

        $byProduct = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereDate('sales.created_at', $date)
            ->whereNull('sales.deleted_at')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($cashierId, fn ($q) => $q->where('sales.cashier_id', $cashierId))
            ->selectRaw('products.id, products.name, products.sku, SUM(sale_items.qty) as units_sold, SUM(sale_items.subtotal) as revenue')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue')
            ->get();

        $byPayment = $base()
            ->selectRaw('payment_method, SUM(grand_total) as total, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get();

        $data = compact('date', 'totals', 'byHour', 'byProduct', 'byPayment');

        if ($request->get('export') === 'pdf') {
            return $this->pdfExport('reports.pdf.daily-sales', $data, "daily-sales-{$date}.pdf");
        }
        if ($request->get('export') === 'excel') {
            $rows = $byProduct->map(fn ($r) => [$r->name, $r->sku, $r->units_sold, $r->revenue])->toArray();

            return $this->excelExport("Daily Sales {$date}", ['Product', 'SKU', 'Units Sold', 'Revenue'], $rows, "daily-sales-{$date}.xlsx");
        }

        $branches = Warehouse::active()->get();
        $cashiers = User::where('tenant_id', $tenantId)->get();

        return view('reports.daily-sales', compact('data', 'branches', 'cashiers', 'date', 'branchId', 'cashierId'));
    }

    // ── 2. SALES TREND ───────────────────────────────────────────────────────

    public function salesTrend(Request $request): mixed
    {
        $period = $request->get('period', 'monthly');
        $branchId = $request->integer('branch_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;

        $groupExpr = $this->exprDateGroup('sales.created_at', $period);

        $trend = DB::table('sales')
            ->leftJoin('sale_items', 'sale_items.sale_id', '=', 'sales.id')
            ->leftJoin('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->selectRaw("{$groupExpr} as period, SUM(sales.grand_total) as revenue, COUNT(DISTINCT sales.id) as transactions")
            ->groupByRaw($groupExpr)
            ->orderByRaw($groupExpr)
            ->get();

        if ($request->get('export') === 'excel') {
            $rows = $trend->map(fn ($r) => [$r->period, $r->revenue, $r->transactions])->toArray();

            return $this->excelExport('Sales Trend', ['Period', 'Revenue', 'Transactions'], $rows, 'sales-trend.xlsx');
        }

        $branches = Warehouse::active()->get();
        $categories = DB::table('product_categories')->where('tenant_id', $this->tenantId())->get();

        return view('reports.sales-trend', compact('trend', 'branches', 'categories', 'period', 'branchId', 'categoryId'));
    }

    // ── 3. PRODUCT PERFORMANCE ───────────────────────────────────────────────

    public function productPerformance(Request $request): mixed
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());
        $branchId = $request->integer('branch_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;

        $query = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->selectRaw(
                'products.id, products.name, products.sku,
                 SUM(sale_items.qty) as units_sold,
                 SUM(sale_items.subtotal) as revenue,
                 SUM(sale_items.qty * sale_items.cost_price) as cogs'
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('revenue');

        if ($query->clone()->count() > self::QUEUE_THRESHOLD) {
            GenerateReportJob::dispatch('product-performance', $request->all(), auth()->id())->onQueue('default');

            return response()->json(['queued' => true, 'message' => 'Report queued — you will be notified when ready.']);
        }

        $results = $query->get()->map(function ($r) {
            $r->margin_pct = $r->revenue > 0 ? round(($r->revenue - $r->cogs) / $r->revenue * 100, 1) : 0;

            return $r;
        });

        if ($request->get('export') === 'excel') {
            $data = $results->map(fn ($r) => [$r->name, $r->sku, $r->units_sold, $r->revenue, $r->cogs, $r->margin_pct.'%'])->toArray();

            return $this->excelExport('Product Performance', ['Product', 'SKU', 'Units Sold', 'Revenue', 'COGS', 'Margin%'], $data, 'product-performance.xlsx');
        }

        $branches = Warehouse::active()->get();
        $categories = DB::table('product_categories')->where('tenant_id', $this->tenantId())->get();

        return view('reports.product-performance', compact('results', 'branches', 'categories', 'startDate', 'endDate', 'branchId', 'categoryId'));
    }

    // ── 4. LOW STOCK ─────────────────────────────────────────────────────────

    public function lowStock(Request $request): mixed
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $categoryId = $request->integer('category_id') ?: null;

        $rows = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory.warehouse_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'products.supplier_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('inventory.tenant_id', $this->tenantId())
            ->where('products.is_active', 1)
            ->whereRaw('inventory.quantity <= products.reorder_level')
            ->when($warehouseId, fn ($q) => $q->where('inventory.warehouse_id', $warehouseId))
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->selectRaw(
                'products.id, products.name, products.sku, product_categories.name as category,
                 inventory.quantity as current_qty, products.reorder_level,
                 '.$this->exprGreatest('products.reorder_level - inventory.quantity', '0').' as suggested_order_qty,
                 suppliers.name as supplier_name, warehouses.name as warehouse_name'
            )
            ->orderByRaw('inventory.quantity / NULLIF(products.reorder_level, 1) ASC')
            ->get();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->name, $r->sku, $r->warehouse_name, $r->current_qty, $r->reorder_level, $r->suggested_order_qty, $r->supplier_name ?? '—'])->toArray();

            return $this->excelExport('Low Stock', ['Product', 'SKU', 'Warehouse', 'Current Qty', 'Reorder Level', 'Suggested Order', 'Supplier'], $data, 'low-stock.xlsx');
        }
        if ($request->get('export') === 'pdf') {
            return $this->pdfExport('reports.pdf.table', [
                'title' => 'Low Stock Report',
                'headers' => ['Product', 'SKU', 'Warehouse', 'Current Qty', 'Reorder Level', 'Suggested Order', 'Supplier'],
                'rows' => $rows->map(fn ($r) => [$r->name, $r->sku, $r->warehouse_name, $r->current_qty, $r->reorder_level, $r->suggested_order_qty, $r->supplier_name ?? '—'])->toArray(),
            ], 'low-stock.pdf');
        }

        $warehouses = Warehouse::active()->get();
        $categories = DB::table('product_categories')->where('tenant_id', $this->tenantId())->get();

        return view('reports.low-stock-v2', compact('rows', 'warehouses', 'categories', 'warehouseId', 'categoryId'));
    }

    // ── 5. DEAD STOCK ────────────────────────────────────────────────────────

    public function deadStock(Request $request): mixed
    {
        $days = max(1, (int) $request->get('days_no_movement', 60));
        $cutoff = now()->subDays($days)->toDateTimeString();

        $rows = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory.warehouse_id')
            ->leftJoin('inventory_movements as lm', function ($join) use ($cutoff) {
                $join->on('lm.product_id', '=', 'inventory.product_id')
                    ->on('lm.warehouse_id', '=', 'inventory.warehouse_id')
                    ->where('lm.created_at', '>=', $cutoff);
            })
            ->where('inventory.tenant_id', $this->tenantId())
            ->where('inventory.quantity', '>', 0)
            ->whereNull('lm.id')
            ->selectRaw(
                'products.name, products.sku, warehouses.name as warehouse_name,
                 inventory.quantity, inventory.unit_cost,
                 inventory.quantity * inventory.unit_cost as value_at_cost,
                 (SELECT MAX(im2.created_at) FROM inventory_movements im2
                  WHERE im2.product_id = inventory.product_id
                  AND im2.warehouse_id = inventory.warehouse_id) as last_movement_date'
            )
            ->orderByDesc('value_at_cost')
            ->get();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->name, $r->sku, $r->warehouse_name, $r->quantity, $r->value_at_cost, $r->last_movement_date])->toArray();

            return $this->excelExport("Dead Stock (>{$days} days)", ['Product', 'SKU', 'Warehouse', 'Qty', 'Value (Cost)', 'Last Movement'], $data, 'dead-stock.xlsx');
        }

        $branches = Warehouse::active()->get();

        return view('reports.dead-stock', compact('rows', 'branches', 'days'));
    }

    // ── 6. INVENTORY VALUATION ───────────────────────────────────────────────

    public function inventoryValuation(Request $request): mixed
    {
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $asOfDate = $request->get('as_of_date', today()->toDateString());

        $rows = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory.warehouse_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('inventory.tenant_id', $this->tenantId())
            ->when($warehouseId, fn ($q) => $q->where('inventory.warehouse_id', $warehouseId))
            ->selectRaw(
                'products.id, products.name, products.sku,
                 product_categories.name as category, warehouses.name as warehouse_name,
                 inventory.quantity as qty, inventory.unit_cost as avg_cost,
                 inventory.quantity * inventory.unit_cost as total_value,
                 products.selling_price,
                 inventory.quantity * products.selling_price as retail_value'
            )
            ->orderBy('product_categories.name')
            ->orderBy('products.name')
            ->get();

        $grandTotal = $rows->sum('total_value');
        $retailTotal = $rows->sum('retail_value');
        $byCategory = $rows->groupBy('category')->map(fn ($g) => [
            'total_value' => $g->sum('total_value'),
            'retail_value' => $g->sum('retail_value'),
            'qty' => $g->sum('qty'),
        ]);

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->name, $r->sku, $r->category, $r->warehouse_name, $r->qty, $r->avg_cost, $r->total_value])->toArray();

            return $this->excelExport('Inventory Valuation', ['Product', 'SKU', 'Category', 'Warehouse', 'Qty', 'Avg Cost', 'Total Value'], $data, 'inventory-valuation.xlsx');
        }
        if ($request->get('export') === 'pdf') {
            return $this->pdfExport('reports.pdf.table', [
                'title' => "Inventory Valuation as of {$asOfDate}",
                'headers' => ['Product', 'SKU', 'Category', 'Warehouse', 'Qty', 'Avg Cost', 'Total Value'],
                'rows' => $rows->map(fn ($r) => [$r->name, $r->sku, $r->category ?? '—', $r->warehouse_name, number_format($r->qty, 2), number_format($r->avg_cost, 2), number_format($r->total_value, 2)])->toArray(),
            ], 'inventory-valuation.pdf');
        }

        $warehouses = Warehouse::active()->get();

        return view('reports.inventory-valuation', compact('rows', 'grandTotal', 'retailTotal', 'byCategory', 'warehouses', 'warehouseId', 'asOfDate'));
    }

    // ── 7. PURCHASE SUMMARY ──────────────────────────────────────────────────

    public function purchaseSummary(Request $request): mixed
    {
        $supplierId = $request->integer('supplier_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());

        $rows = DB::table('purchase_orders')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->leftJoin('goods_received_notes', 'goods_received_notes.purchase_order_id', '=', 'purchase_orders.id')
            ->where('purchase_orders.tenant_id', $this->tenantId())
            ->whereBetween(DB::raw('DATE(purchase_orders.order_date)'), [$startDate, $endDate])
            ->when($supplierId, fn ($q) => $q->where('purchase_orders.supplier_id', $supplierId))
            ->when($branchId, fn ($q) => $q->where('purchase_orders.branch_id', $branchId))
            ->selectRaw(
                'suppliers.id, suppliers.name as supplier_name,
                 COUNT(DISTINCT purchase_orders.id) as order_count,
                 SUM(purchase_orders.total_amount) as total_value,
                 AVG('.$this->exprDateDiff('goods_received_notes.received_at', 'purchase_orders.order_date').') as avg_lead_time'
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_value')
            ->get();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->supplier_name, $r->order_count, number_format($r->total_value, 2), round($r->avg_lead_time ?? 0).' days'])->toArray();

            return $this->excelExport('Purchase Summary', ['Supplier', 'Orders', 'Total Value', 'Avg Lead Time'], $data, 'purchase-summary.xlsx');
        }

        $suppliers = Supplier::active()->get();
        $branches = Warehouse::active()->get();

        return view('reports.purchase-summary', compact('rows', 'suppliers', 'branches', 'supplierId', 'branchId', 'startDate', 'endDate'));
    }

    // ── 8. GRN VS ORDERED ────────────────────────────────────────────────────

    public function grnVsOrdered(Request $request): mixed
    {
        $poId = $request->integer('po_id') ?: null;
        $supplierId = $request->integer('supplier_id') ?: null;
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());

        $rows = DB::table('purchase_order_items')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_items.purchase_order_id')
            ->join('products', 'products.id', '=', 'purchase_order_items.product_id')
            ->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')
            ->where('purchase_orders.tenant_id', $this->tenantId())
            ->whereBetween(DB::raw('DATE(purchase_orders.order_date)'), [$startDate, $endDate])
            ->when($poId, fn ($q) => $q->where('purchase_orders.id', $poId))
            ->when($supplierId, fn ($q) => $q->where('purchase_orders.supplier_id', $supplierId))
            ->selectRaw(
                'purchase_orders.reference_no as po_ref, suppliers.name as supplier_name,
                 products.name as product_name, products.sku,
                 purchase_order_items.quantity_ordered, purchase_order_items.quantity_received,
                 (purchase_order_items.quantity_ordered - purchase_order_items.quantity_received) as variance,
                 CASE WHEN purchase_order_items.quantity_ordered > 0
                      THEN ROUND(purchase_order_items.quantity_received / purchase_order_items.quantity_ordered * 100, 1)
                      ELSE 0 END as fulfillment_pct'
            )
            ->orderBy('purchase_orders.order_date', 'desc')
            ->get();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->po_ref, $r->supplier_name, $r->product_name, $r->sku, $r->quantity_ordered, $r->quantity_received, $r->variance, $r->fulfillment_pct.'%'])->toArray();

            return $this->excelExport('GRN vs Ordered', ['PO Ref', 'Supplier', 'Product', 'SKU', 'Ordered', 'Received', 'Variance', 'Fulfillment%'], $data, 'grn-vs-ordered.xlsx');
        }

        $suppliers = Supplier::active()->get();
        $pos = PurchaseOrder::with('supplier')->latest('order_date')->limit(200)->get();

        return view('reports.grn-vs-ordered', compact('rows', 'suppliers', 'pos', 'poId', 'supplierId', 'startDate', 'endDate'));
    }

    // ── 9. P&L (delegates to FinancialController) ────────────────────────────

    public function pnl(Request $request): mixed
    {
        return app(FinancialController::class)->incomeStatement($request);
    }

    // ── 10. CASH FLOW (delegates to FinancialController) ─────────────────────

    public function cashFlow(Request $request): mixed
    {
        return app(FinancialController::class)->cashFlow($request);
    }

    // ── 11. EXPENSE BREAKDOWN ────────────────────────────────────────────────

    public function expenseBreakdown(Request $request): mixed
    {
        $branchId = $request->integer('branch_id') ?: null;
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());

        $expenses = DB::table('expenses')
            ->where('tenant_id', $this->tenantId())
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->whereBetween(DB::raw('DATE(expense_date)'), [$startDate, $endDate])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $grandTotal = (float) $expenses->sum('total');

        $rows = $expenses->map(function ($e) use ($grandTotal) {
            $e->pct = $grandTotal > 0 ? round($e->total / $grandTotal * 100, 1) : 0;

            return $e;
        });

        $budgets = DB::table('expense_budgets')
            ->where('tenant_id', $this->tenantId())
            ->whereBetween('month', [
                now()->parse($startDate)->startOfMonth(),
                now()->parse($endDate)->startOfMonth(),
            ])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('category, SUM(budget_amount) as budget')
            ->groupBy('category')
            ->pluck('budget', 'category');

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->category, number_format($r->total, 2), $r->pct.'%', number_format($budgets[$r->category] ?? 0, 2)])->toArray();

            return $this->excelExport('Expense Breakdown', ['Category', 'Total', '% of All', 'Budget'], $data, 'expense-breakdown.xlsx');
        }

        $branches = Warehouse::active()->get();

        return view('reports.expense-breakdown', compact('rows', 'grandTotal', 'budgets', 'branches', 'branchId', 'startDate', 'endDate'));
    }

    // ── 12. VAT (delegates to FinancialController) ───────────────────────────

    public function vat(Request $request): mixed
    {
        return app(FinancialController::class)->vatReport($request);
    }

    // ── 13. EMPLOYEE PERFORMANCE ─────────────────────────────────────────────

    public function employeePerformance(Request $request): mixed
    {
        $cashierId = $request->integer('cashier_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());

        $rows = DB::table('sales')
            ->join('users', 'users.id', '=', 'sales.cashier_id')
            ->leftJoin('sale_returns', 'sale_returns.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $this->tenantId())
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate])
            ->when($cashierId, fn ($q) => $q->where('sales.cashier_id', $cashierId))
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->selectRaw(
                'users.id, users.name as cashier_name,
                 COUNT(DISTINCT sales.id) as transactions,
                 SUM(sales.grand_total) as revenue,
                 AVG(sales.grand_total) as avg_transaction,
                 SUM(sales.discount) as discount_given,
                 COUNT(sale_returns.id) as returns'
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r->cashier_name, $r->transactions, number_format($r->revenue, 2), number_format($r->avg_transaction, 2), number_format($r->discount_given, 2), $r->returns])->toArray();

            return $this->excelExport('Employee Performance', ['Cashier', 'Transactions', 'Revenue', 'Avg Transaction', 'Discounts', 'Returns'], $data, 'employee-performance.xlsx');
        }

        $cashiers = User::where('tenant_id', $this->tenantId())->get();
        $branches = Warehouse::active()->get();

        return view('reports.employee-performance', compact('rows', 'cashiers', 'branches', 'cashierId', 'branchId', 'startDate', 'endDate'));
    }

    // ── 14. AUDIT VARIANCE ───────────────────────────────────────────────────

    public function auditVariance(Request $request): mixed
    {
        $auditId = $request->integer('audit_id') ?: null;
        $branchId = $request->integer('branch_id') ?: null;

        $auditItems = collect();
        $audit = null;

        if ($auditId) {
            $audit = InventoryAudit::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId())
                ->findOrFail($auditId);

            $auditItems = DB::table('inventory_audit_items')
                ->join('products', 'products.id', '=', 'inventory_audit_items.product_id')
                ->where('inventory_audit_items.audit_id', $auditId)
                ->whereNotNull('inventory_audit_items.physical_qty')
                ->selectRaw(
                    'products.name, products.sku,
                     inventory_audit_items.system_qty, inventory_audit_items.physical_qty,
                     (inventory_audit_items.physical_qty - inventory_audit_items.system_qty) as variance,
                     inventory_audit_items.notes'
                )
                ->get();

            if ($request->get('export') === 'excel') {
                $data = $auditItems->map(fn ($r) => [$r->name, $r->sku, $r->system_qty, $r->physical_qty, $r->variance, $r->notes])->toArray();

                return $this->excelExport("Audit #{$auditId} Variance", ['Product', 'SKU', 'System Qty', 'Physical Qty', 'Variance', 'Notes'], $data, "audit-variance-{$auditId}.xlsx");
            }
        }

        $audits = InventoryAudit::when($branchId, fn ($q) => $q->where('branch_id', $branchId))->latest()->get();
        $branches = Warehouse::active()->get();

        return view('reports.audit-variance', compact('audit', 'auditItems', 'audits', 'branches', 'auditId', 'branchId'));
    }

    // ── 15. CUSTOMER HISTORY ─────────────────────────────────────────────────

    public function customerHistory(Request $request): mixed
    {
        $customerId = $request->integer('customer_id') ?: null;
        $startDate = $request->get('start_date', now()->subMonths(3)->toDateString());
        $endDate = $request->get('end_date', today()->toDateString());

        $sales = collect();
        $stats = null;
        $customer = null;

        if ($customerId) {
            $customer = Customer::find($customerId);

            $sales = DB::table('sales')
                ->where('tenant_id', $this->tenantId())
                ->where('customer_id', $customerId)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
                ->orderByDesc('created_at')
                ->get();

            $stats = [
                'total_spend' => $sales->sum('grand_total'),
                'avg_order_value' => $sales->avg('grand_total') ?? 0,
                'transaction_count' => $sales->count(),
                'last_purchase' => $sales->first()?->created_at,
            ];

            if ($request->get('export') === 'excel') {
                $data = $sales->map(fn ($r) => [$r->receipt_no, $r->payment_method, number_format($r->grand_total, 2), $r->created_at])->toArray();

                return $this->excelExport('Customer History', ['Receipt No', 'Payment Method', 'Amount', 'Date'], $data, "customer-history-{$customerId}.xlsx");
            }
        }

        $customers = Customer::where('is_active', true)->orderBy('name')->get();

        return view('reports.customer-history', compact('sales', 'stats', 'customer', 'customers', 'customerId', 'startDate', 'endDate'));
    }

    // ── 16. SUPPLIER AGING ───────────────────────────────────────────────────

    public function supplierAging(Request $request): mixed
    {
        $supplierId = $request->integer('supplier_id') ?: null;
        $asOfDate = $request->get('as_of_date', today()->toDateString());

        $suppliers = Supplier::active()
            ->when($supplierId, fn ($q) => $q->where('id', $supplierId))
            ->get();

        $rows = $suppliers->map(function (Supplier $s) {
            $aging = $s->getAgingAnalysis();

            return [
                'supplier' => $s->name,
                'code' => $s->code,
                'current' => $aging['current'],
                'days_30' => $aging['days_30'],
                'days_60' => $aging['days_60'],
                'days_90_plus' => $aging['days_90_plus'],
                'total' => array_sum($aging),
            ];
        })->filter(fn ($r) => $r['total'] > 0)->values();

        if ($request->get('export') === 'excel') {
            $data = $rows->map(fn ($r) => [$r['supplier'], $r['code'], number_format($r['current'], 2), number_format($r['days_30'], 2), number_format($r['days_60'], 2), number_format($r['days_90_plus'], 2), number_format($r['total'], 2)])->toArray();

            return $this->excelExport('Supplier Aging', ['Supplier', 'Code', 'Current', '1-30 Days', '31-60 Days', '60+ Days', 'Total'], $data, "supplier-aging-{$asOfDate}.xlsx");
        }

        $allSuppliers = Supplier::active()->get();

        return view('reports.supplier-aging', compact('rows', 'allSuppliers', 'supplierId', 'asOfDate'));
    }

    // ── SCHEDULED REPORTS ────────────────────────────────────────────────────

    public function schedules()
    {
        $schedules = ReportSchedule::where('user_id', auth()->id())->latest()->get();

        return view('reports.schedules', compact('schedules'));
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $request->validate([
            'report_type' => 'required|string',
            'frequency' => 'required|in:daily,weekly,monthly',
            'email' => 'required|email',
        ]);

        ReportSchedule::create([
            'user_id' => auth()->id(),
            'report_type' => $request->input('report_type'),
            'params' => $request->input('params'),
            'frequency' => $request->input('frequency'),
            'email' => $request->input('email'),
        ]);

        return back()->with('success', 'Report schedule created.');
    }

    // ── LEGACY STUBS (keep existing routes working) ──────────────────────────

    public function stockOnHand(Request $request)
    {
        $balances = StockBalance::with(['product.unit', 'product.category', 'warehouse'])
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->search, fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$request->search}%")
                ->orWhere('sku', 'like', "%{$request->search}%")
            ))
            ->paginate(30)
            ->withQueryString();

        $warehouses = Warehouse::active()->get();
        $totalValue = StockBalance::join('products', 'products.id', '=', 'stock_balances.product_id')
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->selectRaw('SUM(stock_balances.quantity_available * products.cost_price) as total')
            ->value('total') ?? 0;

        return view('reports.stock-on-hand', compact('balances', 'warehouses', 'totalValue'));
    }

    public function expiry(Request $request)
    {
        $days = (int) ($request->days ?? 30);
        $categoryId = $request->integer('category_id') ?: null;

        $expiringSoon = ProductBatch::with('product.unit', 'product.category')
            ->expiringSoon($days)
            ->when($categoryId, fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('category_id', $categoryId)))
            ->orderBy('expiry_date')
            ->get();

        $expired = ProductBatch::with('product.unit')
            ->expired()
            ->when($categoryId, fn ($q) => $q->whereHas('product', fn ($q2) => $q2->where('category_id', $categoryId)))
            ->orderBy('expiry_date', 'desc')
            ->get();

        $categories = Category::orderBy('name')->get(['id', 'name']);

        return view('reports.expiry', compact('expiringSoon', 'expired', 'days', 'categories', 'categoryId'));
    }

    public function flagBatchForPromotion(Request $request, ProductBatch $batch)
    {
        $batch->update(['notes' => ($batch->notes ? $batch->notes.' | ' : '').'[Flagged for Promotion '.now()->toDateString().']']);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'flagged_for_promotion',
            'model_type' => ProductBatch::class,
            'model_id' => $batch->id,
            'new_values' => ['notes' => $batch->notes],
        ]);

        return back()->with('success', "Batch #{$batch->batch_number} flagged for promotion.");
    }

    public function valuation(Request $request)
    {
        return $this->inventoryValuation($request);
    }

    public function purchases(Request $request)
    {
        $orders = PurchaseOrder::with(['supplier', 'warehouse', 'createdBy'])
            ->when($request->from, fn ($q) => $q->whereDate('order_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('order_date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest('order_date')
            ->get();
        $totalAmount = $orders->sum('total_amount');
        $statuses = PurchaseOrder::STATUSES;

        return view('reports.purchases', compact('orders', 'totalAmount', 'statuses'));
    }

    public function sales(Request $request)
    {
        $orders = SalesOrder::with(['customer', 'warehouse', 'createdBy'])
            ->when($request->from, fn ($q) => $q->whereDate('order_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('order_date', '<=', $request->to))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->latest('order_date')
            ->get();
        $totalAmount = $orders->sum('total_amount');
        $statuses = SalesOrder::STATUSES;

        return view('reports.sales', compact('orders', 'totalAmount', 'statuses'));
    }

    public function movements(Request $request)
    {
        $transactions = InventoryTransaction::with(['items.product.unit', 'warehouse', 'createdBy'])
            ->when($request->product_id, fn ($q) => $q->whereHas('items', fn ($iq) => $iq->where('product_id', $request->product_id)))
            ->when($request->warehouse_id, fn ($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->type, fn ($q) => $q->where('transaction_type', $request->type))
            ->when($request->from, fn ($q) => $q->whereDate('transaction_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('transaction_date', '<=', $request->to))
            ->latest('transaction_date')
            ->paginate(30)
            ->withQueryString();

        $products = Product::active()->orderBy('name')->get();
        $warehouses = Warehouse::active()->get();
        $types = InventoryTransaction::TYPES;

        return view('reports.movements', compact('transactions', 'products', 'warehouses', 'types'));
    }

    // ── EXPORT HELPERS ────────────────────────────────────────────────────────

    private function pdfExport(string $view, array $data, string $filename): Response
    {
        $data['tenant'] = auth()->user()->tenant ?? null;
        $data['generated_at'] = now()->format('d M Y H:i');

        $pdf = Pdf::loadView($view, $data)->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function excelExport(string $title, array $headers, array $rows, string $filename): Response
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rpt_').'.xlsx';

        $writer = new XlsxWriter;
        $writer->openToFile($tmpFile);
        $writer->addRow(Row::fromValues([$title]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }
        $writer->close();

        $content = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }

    // ── Cross-DB expression helpers (MySQL production / SQLite tests) ─────────

    private function isSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }

    private function exprHour(string $col): string
    {
        return $this->isSqlite()
            ? "CAST(strftime('%H', {$col}) AS INTEGER)"
            : "HOUR({$col})";
    }

    private function exprDateGroup(string $col, string $period): string
    {
        if ($this->isSqlite()) {
            return match ($period) {
                'weekly' => "strftime('%Y-W%W', {$col})",
                'yearly' => "strftime('%Y', {$col})",
                default => "strftime('%Y-%m', {$col})",
            };
        }

        return match ($period) {
            'weekly' => "DATE_FORMAT({$col}, '%x-W%v')",
            'yearly' => "YEAR({$col})",
            default => "DATE_FORMAT({$col}, '%Y-%m')",
        };
    }

    private function exprGreatest(string $a, string $b): string
    {
        return $this->isSqlite() ? "MAX({$a}, {$b})" : "GREATEST({$a}, {$b})";
    }

    private function exprDateDiff(string $later, string $earlier): string
    {
        return $this->isSqlite()
            ? "CAST(JULIANDAY({$later}) - JULIANDAY({$earlier}) AS INTEGER)"
            : "DATEDIFF({$later}, {$earlier})";
    }
}
