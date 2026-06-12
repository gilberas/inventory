<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFinancialReportJob;
use App\Listeners\InvalidateFinancialCache;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

class FinancialController extends Controller
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function db(): ConnectionInterface
    {
        if (app()->environment('testing') || ! env('DB_READ_HOST')) {
            return DB::connection();
        }

        return DB::connection('mysql-read');
    }

    private function tenantId(): int
    {
        return (int) auth()->user()->tenant_id;
    }

    private function resolveDateRange(Request $request): array
    {
        if ($request->start_date && $request->end_date) {
            return [$request->start_date, $request->end_date];
        }

        return match ($request->get('period', 'month')) {
            'quarter' => [
                now()->startOfQuarter()->toDateString(),
                now()->endOfQuarter()->toDateString(),
            ],
            'year' => [
                now()->startOfYear()->toDateString(),
                now()->endOfYear()->toDateString(),
            ],
            default => [
                now()->startOfMonth()->toDateString(),
                now()->endOfMonth()->toDateString(),
            ],
        };
    }

    private function cacheKey(
        string $report,
        int    $tenantId,
        ?int   $branchId,
        string $period,
        string $startDate,
        string $endDate,
    ): string {
        $version = (int) Cache::get("tenant:{$tenantId}:report:financial:version", 0);
        $hash    = md5($startDate . $endDate);

        return "tenant:{$tenantId}:report:financial:{$branchId}:{$period}:{$hash}:{$report}:v{$version}";
    }

    private function isLargeDateRange(string $startDate, string $endDate): bool
    {
        return Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) > 365;
    }

    // ── Income Statement ──────────────────────────────────────────────────────

    public function incomeStatement(Request $request)
    {
        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        $period                 = $request->get('period', 'month');
        [$startDate, $endDate]  = $this->resolveDateRange($request);

        if ($this->isLargeDateRange($startDate, $endDate)) {
            GenerateFinancialReportJob::dispatch(
                $tenantId, $branchId, 'income-statement', $startDate, $endDate, auth()->id()
            );

            return response()->json([
                'queued'  => true,
                'message' => 'Report is being generated. You will be notified when ready.',
            ]);
        }

        $key  = $this->cacheKey('pnl', $tenantId, $branchId, $period, $startDate, $endDate);
        $data = Cache::remember($key, 600, fn () => $this->computeIncomeStatement(
            $tenantId, $branchId, $startDate, $endDate
        ));

        return view('reports.financial.income-statement', compact(
            'data', 'startDate', 'endDate', 'branchId', 'period'
        ));
    }

    public function computeIncomeStatement(int $tenantId, ?int $branchId, string $startDate, string $endDate): array
    {
        $db = $this->db();

        $revenue = (float) $db->table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween(DB::raw('DATE(created_at)'), [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->sum('grand_total');

        $returns = (float) $db->table('sale_returns')
            ->join('sales', 'sales.id', '=', 'sale_returns.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereNull('sale_returns.deleted_at')
            ->whereBetween(DB::raw('DATE(sale_returns.created_at)'), [$startDate, $endDate])
            ->sum('sale_returns.total_refund');

        $netRevenue = $revenue - $returns;

        $cogs = (float) $db->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

        $grossProfit = $netRevenue - $cogs;
        $grossMargin = $netRevenue > 0 ? round(($grossProfit / $netRevenue) * 100, 2) : 0.0;

        $opexRows = $db->table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->when($branchId, fn ($q) => $q->where('warehouse_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->get();

        $opexByCategory = $opexRows->pluck('total', 'category')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $totalOpex  = array_sum($opexByCategory);
        $netProfit  = $grossProfit - $totalOpex;

        return compact(
            'revenue', 'returns', 'netRevenue',
            'cogs', 'grossProfit', 'grossMargin',
            'opexByCategory', 'totalOpex', 'netProfit'
        );
    }

    // ── Cash Flow ─────────────────────────────────────────────────────────────

    public function cashFlow(Request $request)
    {
        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        $period                 = $request->get('period', 'month');
        [$startDate, $endDate]  = $this->resolveDateRange($request);

        if ($this->isLargeDateRange($startDate, $endDate)) {
            GenerateFinancialReportJob::dispatch(
                $tenantId, $branchId, 'cash-flow', $startDate, $endDate, auth()->id()
            );

            return response()->json([
                'queued'  => true,
                'message' => 'Report is being generated. You will be notified when ready.',
            ]);
        }

        $key  = $this->cacheKey('cashflow', $tenantId, $branchId, $period, $startDate, $endDate);
        $data = Cache::remember($key, 600, fn () => $this->computeCashFlow(
            $tenantId, $branchId, $startDate, $endDate
        ));

        return view('reports.financial.cash-flow', compact(
            'data', 'startDate', 'endDate', 'branchId', 'period'
        ));
    }

    public function computeCashFlow(int $tenantId, ?int $branchId, string $startDate, string $endDate): array
    {
        $db          = $this->db();
        $cashMethods = ['cash', 'mpesa', 'airtel', 'tigo', 'halo'];

        // Cash In: completed sale payments via cash-like methods
        $cashIn = (float) $db->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('method', $cashMethods)
            ->where(fn ($q) => $q
                ->where('payable_type', 'App\Models\Sale')
                ->orWhere('payable_type', 'App\\Models\\Sale')
            )
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // Cash Out: supplier invoice/PO payments
        $supplierCashOut = (float) $db->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('method', $cashMethods)
            ->where(fn ($q) => $q
                ->whereIn('payable_type', [
                    'App\Models\SupplierInvoice',
                    'App\\Models\\SupplierInvoice',
                    'App\Models\PurchaseOrder',
                    'App\\Models\\PurchaseOrder',
                ])
            )
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        // Cash Out: approved expenses
        $expenseCashOut = (float) $db->table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->when($branchId, fn ($q) => $q->where('warehouse_id', $branchId))
            ->whereBetween('expense_date', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->sum('amount');

        $cashOut = $supplierCashOut + $expenseCashOut;
        $netCash = $cashIn - $cashOut;

        return compact('cashIn', 'supplierCashOut', 'expenseCashOut', 'cashOut', 'netCash');
    }

    // ── Balance Sheet ─────────────────────────────────────────────────────────

    public function balanceSheet(Request $request)
    {
        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        $period                 = $request->get('period', 'month');
        [$startDate, $endDate]  = $this->resolveDateRange($request);

        $key  = $this->cacheKey('bsheet', $tenantId, $branchId, $period, $startDate, $endDate);
        $data = Cache::remember($key, 600, fn () => $this->computeBalanceSheet(
            $tenantId, $branchId, $startDate, $endDate
        ));

        return view('reports.financial.balance-sheet', compact(
            'data', 'startDate', 'endDate', 'branchId', 'period'
        ));
    }

    public function computeBalanceSheet(int $tenantId, ?int $branchId, string $startDate, string $endDate): array
    {
        $db = $this->db();

        // Assets — Inventory value: qty_available × cost_price per warehouse
        $inventoryRows = $db->table('stock_balances')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('products.tenant_id', $tenantId)
            ->whereNull('products.deleted_at')
            ->when($branchId, fn ($q) => $q->where('stock_balances.warehouse_id', $branchId))
            ->selectRaw('warehouses.name as warehouse, SUM(stock_balances.quantity_available * products.cost_price) as value')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->get();

        $inventoryByWarehouse = $inventoryRows
            ->pluck('value', 'warehouse')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $inventoryTotal = (float) array_sum($inventoryByWarehouse);

        // Assets — Cash: completed cash payments received in period
        $cashAssets = (float) $db->table('payments')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('method', ['cash', 'mpesa', 'airtel', 'tigo', 'halo'])
            ->where(fn ($q) => $q
                ->where('payable_type', 'App\Models\Sale')
                ->orWhere('payable_type', 'App\\Models\\Sale')
            )
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->sum('amount');

        $totalAssets = $inventoryTotal + $cashAssets;

        // Liabilities — Supplier payables
        $payables = (float) $db->table('suppliers')
            ->where('tenant_id', $tenantId)
            ->where('balance', '>', 0)
            ->whereNull('deleted_at')
            ->sum('balance');

        // Liabilities — Customer advances (negative balance = credit on account)
        $customerAdvances = (float) $db->table('customers')
            ->where('tenant_id', $tenantId)
            ->where('balance', '<', 0)
            ->whereNull('deleted_at')
            ->sum(DB::raw('ABS(balance)'));

        $totalLiabilities = $payables + $customerAdvances;
        $equity           = $totalAssets - $totalLiabilities;

        return compact(
            'inventoryByWarehouse', 'inventoryTotal',
            'cashAssets', 'totalAssets',
            'payables', 'customerAdvances', 'totalLiabilities',
            'equity'
        );
    }

    // ── VAT Report ────────────────────────────────────────────────────────────

    public function vatReport(Request $request)
    {
        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        $period                 = $request->get('period', 'month');
        [$startDate, $endDate]  = $this->resolveDateRange($request);

        if ($this->isLargeDateRange($startDate, $endDate)) {
            GenerateFinancialReportJob::dispatch(
                $tenantId, $branchId, 'vat', $startDate, $endDate, auth()->id()
            );

            return response()->json([
                'queued'  => true,
                'message' => 'Report is being generated. You will be notified when ready.',
            ]);
        }

        $key  = $this->cacheKey('vat', $tenantId, $branchId, $period, $startDate, $endDate);
        $data = Cache::remember($key, 600, fn () => $this->computeVat(
            $tenantId, $branchId, $startDate, $endDate
        ));

        // Guarantee Collection type after cache deserialisation (cached stdClass arrays become plain arrays)
        $data['collectedByRate'] = collect($data['collectedByRate'] ?? []);
        $data['paidByRate']      = collect($data['paidByRate'] ?? []);

        $tenant        = auth()->user()->tenant;
        $sequentialRef = 'VAT-' . now()->format('Ymd') . '-' . str_pad((string) $tenantId, 4, '0', STR_PAD_LEFT);

        return view('reports.financial.vat', compact(
            'data', 'startDate', 'endDate', 'branchId', 'period', 'tenant', 'sequentialRef'
        ));
    }

    public function computeVat(int $tenantId, ?int $branchId, string $startDate, string $endDate): array
    {
        $db = $this->db();

        $vatCollected = (float) $db->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->sum(DB::raw('sale_items.subtotal * products.tax_rate / 100'));

        $vatPaid = (float) $db->table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('products', 'products.id', '=', 'grn_items.product_id')
            ->where('goods_received_notes.tenant_id', $tenantId)
            ->where('goods_received_notes.status', 'confirmed')
            ->when($branchId, fn ($q) => $q->where('goods_received_notes.warehouse_id', $branchId))
            ->whereBetween(DB::raw('DATE(goods_received_notes.received_at)'), [$startDate, $endDate])
            ->whereNull('goods_received_notes.deleted_at')
            ->sum(DB::raw('grn_items.qty_received * grn_items.unit_cost * products.tax_rate / 100'));

        $netVatPayable = $vatCollected - $vatPaid;

        // AC-1: breakdown by tax rate for collected VAT
        $collectedByRate = $db->table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$startDate, $endDate])
            ->whereNull('sales.deleted_at')
            ->where('products.tax_rate', '>', 0)
            ->selectRaw('products.tax_rate, SUM(sale_items.subtotal) AS taxable_amount, SUM(sale_items.subtotal * products.tax_rate / 100) AS vat_amount')
            ->groupBy('products.tax_rate')
            ->orderBy('products.tax_rate')
            ->get();

        // AC-1: breakdown by tax rate for paid VAT
        $paidByRate = $db->table('grn_items')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.grn_id')
            ->join('products', 'products.id', '=', 'grn_items.product_id')
            ->where('goods_received_notes.tenant_id', $tenantId)
            ->where('goods_received_notes.status', 'confirmed')
            ->when($branchId, fn ($q) => $q->where('goods_received_notes.warehouse_id', $branchId))
            ->whereBetween(DB::raw('DATE(goods_received_notes.received_at)'), [$startDate, $endDate])
            ->whereNull('goods_received_notes.deleted_at')
            ->where('products.tax_rate', '>', 0)
            ->selectRaw('products.tax_rate, SUM(grn_items.qty_received * grn_items.unit_cost) AS taxable_amount, SUM(grn_items.qty_received * grn_items.unit_cost * products.tax_rate / 100) AS vat_amount')
            ->groupBy('products.tax_rate')
            ->orderBy('products.tax_rate')
            ->get();

        return compact('vatCollected', 'vatPaid', 'netVatPayable', 'collectedByRate', 'paidByRate');
    }

    // ── Export PDF ────────────────────────────────────────────────────────────

    public function exportPdf(Request $request, string $report)
    {
        abort_if(
            ! in_array($report, ['income-statement', 'cash-flow', 'balance-sheet', 'vat']),
            404
        );

        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        [$startDate, $endDate]  = $this->resolveDateRange($request);
        $tenant                 = auth()->user()->tenant;

        $data = match ($report) {
            'income-statement' => $this->computeIncomeStatement($tenantId, $branchId, $startDate, $endDate),
            'cash-flow'        => $this->computeCashFlow($tenantId, $branchId, $startDate, $endDate),
            'balance-sheet'    => $this->computeBalanceSheet($tenantId, $branchId, $startDate, $endDate),
            'vat'              => $this->computeVat($tenantId, $branchId, $startDate, $endDate),
        };

        $pdf = Pdf::loadView(
            "reports.financial.pdf.{$report}",
            compact('data', 'tenant', 'startDate', 'endDate', 'branchId')
        );

        return $pdf->download("{$report}-{$startDate}-{$endDate}.pdf");
    }

    // ── Export Excel ──────────────────────────────────────────────────────────

    public function exportExcel(Request $request, string $report)
    {
        abort_if(
            ! in_array($report, ['income-statement', 'cash-flow', 'balance-sheet', 'vat']),
            404
        );

        $tenantId               = $this->tenantId();
        $branchId               = $request->integer('branch_id') ?: null;
        [$startDate, $endDate]  = $this->resolveDateRange($request);

        $data = match ($report) {
            'income-statement' => $this->computeIncomeStatement($tenantId, $branchId, $startDate, $endDate),
            'cash-flow'        => $this->computeCashFlow($tenantId, $branchId, $startDate, $endDate),
            'balance-sheet'    => $this->computeBalanceSheet($tenantId, $branchId, $startDate, $endDate),
            'vat'              => $this->computeVat($tenantId, $branchId, $startDate, $endDate),
        };

        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "{$report}-{$startDate}-{$endDate}.xlsx";

        $writer = new Writer();
        $writer->openToFile($filename);

        $writer->addRow(Row::fromValues([
            strtoupper(str_replace('-', ' ', $report)),
            auth()->user()->tenant->name ?? '',
            "Period: {$startDate} to {$endDate}",
            'Generated: ' . now()->toDateTimeString(),
        ]));
        $writer->addRow(Row::fromValues([]));

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    $writer->addRow(Row::fromValues([$k, (float) $v]));
                }
            } else {
                $writer->addRow(Row::fromValues([
                    ucwords(str_replace('_', ' ', $key)),
                    $value,
                ]));
            }
        }

        $writer->close();

        return response()
            ->download($filename, "{$report}-{$startDate}-{$endDate}.xlsx")
            ->deleteFileAfterSend();
    }
}
