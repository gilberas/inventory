<?php

namespace App\Http\Controllers;

use App\Services\DashboardMetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsService $metrics) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        // Super Admin has no tenant — route before the tenant guard below.
        if ($user->hasRole('super_admin')) {
            return $this->superAdminDashboard();
        }

        $tenantId = (int) $user->tenant_id;

        if ($tenantId === 0) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is not linked to a business. Please register again.']);
        }

        // Branch selector: owners see all warehouses; all other roles are locked to their warehouse.
        $canSelectBranch = $user->hasRole('business_owner');

        if ($canSelectBranch) {
            $warehouseId = $request->integer('branch_id') ?: null;
            $warehouses  = DB::table('warehouses')
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->get(['id', 'name', 'code']);
        } else {
            $warehouseId = $user->branch_id ? (int) $user->branch_id : null;
            $warehouses  = collect();
        }

        $branchKey = $warehouseId ?? 'all';
        $cacheKey  = "tenant:{$tenantId}:dashboard:{$branchKey}:" . today()->format('Y-m-d');

        $computed = Cache::remember($cacheKey, 300, function () use ($tenantId, $warehouseId, $canSelectBranch) {
            return [
                'inventory'        => $this->metrics->inventoryMetrics($tenantId, $warehouseId),
                'sales'            => $this->metrics->salesMetrics($tenantId, $warehouseId),
                'purchases'        => $this->metrics->purchaseMetrics($tenantId, $warehouseId),
                'financial'        => $this->metrics->financialMetrics($tenantId, $warehouseId),
                'branches_summary' => ($canSelectBranch && $warehouseId === null)
                    ? $this->metrics->branchesSummary($tenantId)
                    : [],
            ];
        });

        $shared = [
            'inventory'          => $computed['inventory'],
            'sales'              => $computed['sales'],
            'purchases'          => $computed['purchases'],
            'financial'          => $computed['financial'],
            'branches_summary'   => $computed['branches_summary'],
            'warehouses'         => $warehouses,
            'selected_warehouse' => $warehouseId,
            'can_select_branch'  => $canSelectBranch,
            'notification_count' => $user->unreadNotifications()->count(),
        ];

        $role = $user->getRoleNames()->first() ?? 'viewer';

        return match ($role) {
            'business_owner' => view('dashboard.business_owner', $shared),
            'branch_manager' => view('dashboard.branch_manager', array_merge($shared, [
                'pending_requisitions' => $this->pendingRequisitions($tenantId, $warehouseId),
            ])),
            'cashier'     => view('dashboard.cashier', array_merge($shared, [
                'open_session'          => $this->openPosSession($user->id, $tenantId),
                'my_sales_today'        => $this->mySalesToday($user->id, $tenantId),
                'my_transactions_today' => $this->myTransactionsToday($user->id, $tenantId),
                'my_recent_sales'       => $this->myRecentSales($user->id, $tenantId),
            ])),
            'storekeeper' => view('dashboard.storekeeper', array_merge($shared, [
                'low_stock_products'    => $this->lowStockProducts($tenantId, $warehouseId),
                'expiring_products'     => $this->expiringProducts($tenantId, $warehouseId),
                'pending_grns'          => $this->pendingGrns($tenantId, $warehouseId),
                'grns_confirmed_today'  => $this->grnsConfirmedToday($tenantId, $warehouseId),
                'transfers_to_dispatch' => $this->transfersToDispatch($tenantId, $warehouseId),
                'transfers_to_receive'  => $this->transfersToReceive($tenantId, $warehouseId),
            ])),
            'accountant' => view('dashboard.accountant', array_merge($shared, [
                'vat_collected'              => $this->vatCollected($tenantId),
                'vat_paid'                   => $this->vatPaid($tenantId),
                'revenue_last_month'         => $this->revenueLastMonth($tenantId),
                'cogs_this_month'            => $this->cogsThisMonth($tenantId),
                'pending_expense_approvals'  => $this->pendingExpenseApprovals($tenantId),
                'overdue_invoices'           => $this->overdueInvoices($tenantId),
            ])),
            default => view('dashboard.index', $shared),
        };
    }

    // ── Super Admin ───────────────────────────────────────────────────────────

    private function superAdminDashboard(): View
    {
        return view('dashboard.super_admin', [
            'total_tenants'          => DB::table('tenants')->count(),
            'active_tenants'         => DB::table('tenants')->where('status', 'active')->count(),
            'suspended_tenants'      => DB::table('tenants')->where('status', 'suspended')->count(),
            'new_tenants_this_month' => DB::table('tenants')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'total_users'            => DB::table('users')->count(),
            'active_users'           => DB::table('users')->where('status', 'active')->count(),
            'recent_tenants'         => DB::table('tenants')
                ->latest()
                ->limit(10)
                ->get(['id', 'name', 'slug', 'status', 'created_at']),
            'failed_jobs'            => DB::table('failed_jobs')->count(),
            'queue_size'             => DB::table('jobs')->count(),
            'notification_count'     => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    // ── Branch Manager helpers ────────────────────────────────────────────────

    private function pendingRequisitions(int $tenantId, ?int $warehouseId): int
    {
        return DB::table('purchase_requisitions')
            ->where('tenant_id', $tenantId)
            ->where('status', 'submitted')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->count();
    }

    // ── Cashier helpers ───────────────────────────────────────────────────────

    private function openPosSession(int $userId, int $tenantId): ?object
    {
        return DB::table('pos_sessions')
            ->where('tenant_id', $tenantId)
            ->where('cashier_id', $userId)
            ->where('status', 'active')
            ->latest('opened_at')
            ->first();
    }

    private function mySalesToday(int $userId, int $tenantId): float
    {
        return (float) DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('cashier_id', $userId)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->sum('grand_total');
    }

    private function myTransactionsToday(int $userId, int $tenantId): int
    {
        return DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('cashier_id', $userId)
            ->where('status', 'completed')
            ->whereDate('created_at', today())
            ->count();
    }

    private function myRecentSales(int $userId, int $tenantId): \Illuminate\Support\Collection
    {
        return DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('cashier_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'grand_total', 'payment_method', 'status', 'created_at']);
    }

    // ── Storekeeper helpers ───────────────────────────────────────────────────

    private function lowStockProducts(int $tenantId, ?int $warehouseId): \Illuminate\Support\Collection
    {
        return DB::table('products')
            ->join('inventory', 'products.id', '=', 'inventory.product_id')
            ->where('products.tenant_id', $tenantId)
            ->where('products.is_active', true)
            ->whereNull('products.deleted_at')
            ->when($warehouseId, fn ($q) => $q->where('inventory.warehouse_id', $warehouseId))
            ->whereRaw('inventory.quantity <= products.reorder_level')
            ->where('inventory.quantity', '>', 0)
            ->orderByRaw('inventory.quantity / (CASE WHEN products.reorder_level > 0 THEN products.reorder_level ELSE 1 END) ASC')
            ->limit(10)
            ->get(['products.id', 'products.name', 'products.sku', 'products.reorder_level', 'inventory.quantity']);
    }

    private function expiringProducts(int $tenantId, ?int $warehouseId): \Illuminate\Support\Collection
    {
        return DB::table('product_batches')
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('product_batches.deleted_at')
            ->whereNull('products.deleted_at')
            ->whereNotNull('product_batches.expiry_date')
            ->whereBetween('product_batches.expiry_date', [today()->toDateString(), today()->addDays(30)->toDateString()])
            ->when($warehouseId, function ($q) use ($warehouseId) {
                // Scope to products that have stock in the given warehouse
                $productIds = DB::table('stock_balances')
                    ->where('warehouse_id', $warehouseId)
                    ->where('quantity_available', '>', 0)
                    ->pluck('product_id');
                $q->whereIn('product_batches.product_id', $productIds);
            })
            ->orderBy('product_batches.expiry_date')
            ->limit(10)
            ->get(['products.name', 'products.sku', 'product_batches.expiry_date', 'product_batches.quantity']);
    }

    private function pendingGrns(int $tenantId, ?int $warehouseId): \Illuminate\Support\Collection
    {
        return DB::table('goods_received_notes')
            ->where('tenant_id', $tenantId)
            ->where('status', 'draft')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'reference_no', 'purchase_order_id', 'received_at', 'status']);
    }

    private function grnsConfirmedToday(int $tenantId, ?int $warehouseId): int
    {
        return DB::table('goods_received_notes')
            ->where('tenant_id', $tenantId)
            ->where('status', 'confirmed')
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->whereDate('received_at', today())
            ->count();
    }

    private function transfersToDispatch(int $tenantId, ?int $warehouseId): int
    {
        return DB::table('branch_transfers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->when($warehouseId, fn ($q) => $q->where('from_branch_id', $warehouseId))
            ->count();
    }

    private function transfersToReceive(int $tenantId, ?int $warehouseId): int
    {
        return DB::table('branch_transfers')
            ->where('tenant_id', $tenantId)
            ->where('status', 'dispatched')
            ->when($warehouseId, fn ($q) => $q->where('to_branch_id', $warehouseId))
            ->count();
    }

    // ── Accountant helpers ────────────────────────────────────────────────────

    private function vatCollected(int $tenantId): float
    {
        return (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereMonth('sales.created_at', now()->month)
            ->whereYear('sales.created_at', now()->year)
            ->selectRaw('COALESCE(SUM(sale_items.subtotal * COALESCE(products.tax_rate, 0) / 100), 0) as vat')
            ->value('vat');
    }

    private function vatPaid(int $tenantId): float
    {
        return (float) DB::table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('products', 'products.id', '=', 'grn_items.product_id')
            ->where('goods_received_notes.tenant_id', $tenantId)
            ->where('goods_received_notes.status', 'confirmed')
            ->whereMonth('goods_received_notes.received_at', now()->month)
            ->whereYear('goods_received_notes.received_at', now()->year)
            ->selectRaw('COALESCE(SUM(grn_items.qty_received * grn_items.unit_cost * COALESCE(products.tax_rate, 0) / 100), 0) as vat')
            ->value('vat');
    }

    private function revenueLastMonth(int $tenantId): float
    {
        return (float) DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNull('deleted_at')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('grand_total');
    }

    private function cogsThisMonth(int $tenantId): float
    {
        return (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereNull('sales.deleted_at')
            ->whereMonth('sales.created_at', now()->month)
            ->whereYear('sales.created_at', now()->year)
            ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));
    }

    private function pendingExpenseApprovals(int $tenantId): int
    {
        return DB::table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending_approval')
            ->count();
    }

    private function overdueInvoices(int $tenantId): int
    {
        return DB::table('supplier_invoices')
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('due_date', '<', today()->toDateString())
            ->count();
    }
}
