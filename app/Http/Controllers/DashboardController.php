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
        $user     = $request->user();
        $tenantId = (int) $user->tenant_id;

        // Guard: account has no tenant — created before migration ran or corrupt state.
        // Force logout so they can re-register with the new flow.
        if ($tenantId === 0) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is not linked to a business. Please register again.']);
        }

        // Branch selector: owners see all warehouses + consolidated toggle;
        // branch managers are locked to their assigned warehouse.
        $canSelectBranch = $user->hasRole(['super_admin', 'business_owner']);

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

        return view('dashboard.index', [
            'inventory'          => $computed['inventory'],
            'sales'              => $computed['sales'],
            'purchases'          => $computed['purchases'],
            'financial'          => $computed['financial'],
            'branches_summary'   => $computed['branches_summary'],
            'warehouses'         => $warehouses,
            'selected_warehouse' => $warehouseId,
            'can_select_branch'  => $canSelectBranch,
            'notification_count' => auth()->user()->unreadNotifications()->count(),
        ]);
    }
}
