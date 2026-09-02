<?php

namespace App\Jobs;

use App\Mail\MonthlyPnlReportMail;
use App\Models\Tenant;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendMonthlyPnlReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $tenantId) {}

    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $owner = User::where('tenant_id', $this->tenantId)
            ->role('Super Admin')
            ->first();

        if (! $owner) {
            return;
        }

        $start = now()->subMonth()->startOfMonth()->toDateString();
        $end   = now()->subMonth()->endOfMonth()->toDateString();
        $month = now()->subMonth()->format('F Y');

        $data = $this->computePnl($this->tenantId, $start, $end);

        $pdf = Pdf::loadView('reports.financial.pdf.income-statement', [
            'tenant'      => $tenant,
            'data'        => $data,
            'startDate'   => $start,
            'endDate'     => $end,
            'generatedAt' => now()->toDateTimeString(),
        ])->setPaper('a4', 'portrait');

        $pdfContent = $pdf->output();

        Mail::to($owner->email)->queue(
            new MonthlyPnlReportMail($tenant, $owner, $month, $data, $pdfContent)
        );
    }

    private function computePnl(int $tenantId, string $start, string $end): array
    {
        $revenue = (float) DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween(DB::raw('DATE(created_at)'), [$start, $end])
            ->sum('grand_total');

        $cogs = (float) DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.status', 'completed')
            ->whereBetween(DB::raw('DATE(sales.created_at)'), [$start, $end])
            ->sum(DB::raw('sale_items.qty * sale_items.cost_price'));

        $expenses = (float) DB::table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween(DB::raw('DATE(expense_date)'), [$start, $end])
            ->sum('amount');

        $grossProfit = $revenue - $cogs;
        $netProfit   = $grossProfit - $expenses;

        $topExpenseCategory = DB::table('expenses')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween(DB::raw('DATE(expense_date)'), [$start, $end])
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->value('category') ?? 'N/A';

        return compact('revenue', 'cogs', 'grossProfit', 'expenses', 'netProfit', 'topExpenseCategory');
    }
}
