<?php

namespace App\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * All queries use DB::table() with explicit tenant_id filters because the
 * existing Eloquent models do not yet extend TenantModel. Never use
 * Eloquent model calls here — that would bypass the tenant filter.
 */
class DashboardMetricsService
{
    /**
     * Return the read-replica connection when DB_READ_HOST is set,
     * otherwise fall back to the default connection (safe for tests).
     */
    private function db(): ConnectionInterface
    {
        if (app()->environment('testing') || !env('DB_READ_HOST')) {
            return DB::connection();
        }

        return DB::connection('mysql-read');
    }

    // ── Inventory ─────────────────────────────────────────────────────────────

    public function inventoryMetrics(int $tenantId, ?int $warehouseId): array
    {
        $db = $this->db();

        $totalProducts = $db->table('products')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->count();

        // Reusable closure — each call returns a fresh Builder so callers
        // can freely add additional WHERE clauses without aliasing issues.
        $stockBase = fn () => $db->table('products')
            ->join('stock_balances', 'products.id', '=', 'stock_balances.product_id')
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('stock_balances.warehouse_id', $warehouseId));

        // Low stock: qty > 0 but at or below reorder level (products.minimum_stock)
        $lowStockCount = $stockBase()
            ->whereRaw('stock_balances.quantity_available > 0')
            ->whereRaw('stock_balances.quantity_available <= products.minimum_stock')
            ->distinct('products.id')
            ->count('products.id');

        $outOfStockCount = $stockBase()
            ->where('stock_balances.quantity_available', '<=', 0)
            ->distinct('products.id')
            ->count('products.id');

        // Expiry tracked via product_batches (batch-level expiry_date)
        $expiringSoonCount = $db->table('product_batches')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('product_batches.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNotNull('product_batches.expiry_date')
            ->whereBetween('product_batches.expiry_date', [
                today()->toDateString(),
                today()->addDays(30)->toDateString(),
            ])
            ->when($warehouseId, function ($q) use ($warehouseId, $db) {
                // Scope to batches that have stock in the selected warehouse
                $productIds = $db->table('stock_balances')
                    ->where('warehouse_id', $warehouseId)
                    ->where('quantity_available', '>', 0)
                    ->pluck('product_id');
                $q->whereIn('product_batches.product_id', $productIds);
            })
            ->distinct('product_batches.product_id')
            ->count('product_batches.product_id');

        return compact('totalProducts', 'lowStockCount', 'outOfStockCount', 'expiringSoonCount');
    }

    // ── Sales ─────────────────────────────────────────────────────────────────

    public function salesMetrics(int $tenantId, ?int $warehouseId): array
    {
        $db = $this->db();

        // POS sales — the primary sales flow (§5.9)
        $posBase = fn () => $db->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));

        $salesToday     = (float) $posBase()->whereDate('created_at', today())->sum('grand_total');
        $salesThisWeek  = (float) $posBase()->whereBetween(DB::raw('DATE(created_at)'), [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString(),
        ])->sum('grand_total');
        $salesThisMonth = (float) $posBase()->whereBetween(DB::raw('DATE(created_at)'), [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ])->sum('grand_total');
        $salesThisYear  = (float) $posBase()->whereBetween(DB::raw('DATE(created_at)'), [
            now()->startOfYear()->toDateString(),
            now()->endOfYear()->toDateString(),
        ])->sum('grand_total');

        // Sparkline: 30-day window, zero-padded for days with no sales
        $rawSparkline = $posBase()
            ->where(DB::raw('DATE(created_at)'), '>=', today()->subDays(29)->toDateString())
            ->selectRaw('DATE(created_at) as date, SUM(grand_total) as total')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $salesSparkline = collect();
        for ($i = 29; $i >= 0; $i--) {
            $date = today()->subDays($i)->toDateString();
            $salesSparkline->push([
                'date'  => $date,
                'total' => (float) ($rawSparkline->get($date)->total ?? 0),
            ]);
        }

        // Top 5 products by quantity sold this month
        $monthRange = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        $top5ProductsThisMonth = $db->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('sales.warehouse_id', $warehouseId))
            ->whereBetween(DB::raw('DATE(sales.created_at)'), $monthRange)
            ->selectRaw(
                'sale_items.product_id,
                 products.name,
                 products.sku,
                 SUM(sale_items.qty)      AS total_qty,
                 SUM(sale_items.subtotal) AS total_revenue'
            )
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        return compact(
            'salesToday', 'salesThisWeek', 'salesThisMonth', 'salesThisYear',
            'salesSparkline', 'top5ProductsThisMonth'
        );
    }

    // ── Per-branch summary (business owner consolidated view — BO-1) ──────────

    public function branchesSummary(int $tenantId): array
    {
        $db = $this->db();

        $branches = $db->table('branches')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get(['id', 'name', 'code']);

        $summary = [];
        foreach ($branches as $branch) {
            $todaySales = (float) $db->table('sales')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branch->id)
                ->where('status', 'completed')
                ->whereNull('deleted_at')
                ->whereDate('created_at', today())
                ->sum('grand_total');

            $lowStockCount = $db->table('products')
                ->join('inventory', 'products.id', '=', 'inventory.product_id')
                ->join('warehouses', 'warehouses.id', '=', 'inventory.warehouse_id')
                ->where('products.tenant_id', $tenantId)
                ->where('products.is_active', true)
                ->whereNull('products.deleted_at')
                ->where('warehouses.branch_id', $branch->id)
                ->whereRaw('inventory.quantity <= products.reorder_level')
                ->where('inventory.quantity', '>', 0)
                ->distinct('products.id')
                ->count('products.id');

            $summary[] = [
                'branch_id'       => $branch->id,
                'branch_name'     => $branch->name,
                'today_sales'     => $todaySales,
                'low_stock_count' => $lowStockCount,
            ];
        }

        return $summary;
    }

    // ── Purchasing ────────────────────────────────────────────────────────────

    public function purchaseMetrics(int $tenantId, ?int $warehouseId): array
    {
        $db         = $this->db();
        $monthRange = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        // "sent or received" → APPROVED | RECEIVED | PARTIALLY_RECEIVED
        $purchaseValueThisMonth = (float) $db->table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['APPROVED', 'RECEIVED', 'PARTIALLY_RECEIVED'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereBetween('order_date', $monthRange)
            ->sum('total_amount');

        $pendingPoCount = $db->table('purchase_orders')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['DRAFT', 'PENDING_APPROVAL'])
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->count();

        return compact('purchaseValueThisMonth', 'pendingPoCount');
    }

    // ── Financial ─────────────────────────────────────────────────────────────

    public function financialMetrics(int $tenantId, ?int $warehouseId): array
    {
        $db         = $this->db();
        $monthRange = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];

        $revenueThisMonth = (float) $db->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereBetween(DB::raw('DATE(created_at)'), $monthRange)
            ->sum('grand_total');

        $expensesThisMonth = (float) $db->table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween('expense_date', $monthRange)
            ->sum('amount');

        // COGS uses cost_price stored at sale time on sale_items
        $cogs = (float) $db->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('sales.warehouse_id', $warehouseId))
            ->whereBetween(DB::raw('DATE(sales.created_at)'), $monthRange)
            ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

        $grossProfitThisMonth = $revenueThisMonth - $cogs;

        // customers.balance maintained by payment-recording flow
        $outstandingReceivables = (float) $db->table('customers')
            ->where('tenant_id', $tenantId)
            ->where('balance', '>', 0)
            ->sum('balance');

        $outstandingPayables = (float) $db->table('suppliers')
            ->where('tenant_id', $tenantId)
            ->where('balance', '>', 0)
            ->sum('balance');

        return compact(
            'revenueThisMonth', 'expensesThisMonth', 'grossProfitThisMonth',
            'outstandingReceivables', 'outstandingPayables'
        );
    }
}
