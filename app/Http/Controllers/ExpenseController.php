<?php

namespace App\Http\Controllers;

use App\Events\ExpenseApproved;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\User;
use App\Notifications\ExpenseApprovalNotification;
use App\Notifications\ExpenseRejectedNotification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

class ExpenseController extends Controller
{
    // ── GET /expenses ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Expense::with(['createdBy', 'approvedBy', 'branch'])->latest('expense_date');

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('expense_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('expense_date', '<=', $request->to);
        }

        return response()->json(['data' => $query->paginate(20)->withQueryString()]);
    }

    // ── GET /expenses/summary ─────────────────────────────────────────────────

    public function summary(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);

        $user     = $request->user();
        $month    = $request->input('month');
        $branchId = $request->input('branch_id');

        [$year, $mon] = explode('-', $month);
        $start = Carbon::create((int) $year, (int) $mon, 1)->startOfMonth()->toDateString();
        $end   = Carbon::create((int) $year, (int) $mon, 1)->endOfMonth()->toDateString();

        $query = Expense::where('status', Expense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$start, $end]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $rows       = $query->selectRaw('category, SUM(amount) as total_spent')->groupBy('category')->get();
        $grandTotal = $rows->sum('total_spent');

        // Load matching budgets (whereDate handles stored '2026-06-01 00:00:00' vs '2026-06-01')
        $budgetQuery = ExpenseBudget::whereDate('month', $start);
        if ($branchId) {
            $budgetQuery->where('branch_id', $branchId);
        } else {
            $budgetQuery->where('tenant_id', $user->tenant_id);
        }
        $budgets = $budgetQuery->pluck('budget_amount', 'category');

        $data = $rows->map(function ($row) use ($budgets, $grandTotal) {
            $budget   = isset($budgets[$row->category]) ? (float) $budgets[$row->category] : null;
            $spent    = (float) $row->total_spent;
            $variance = $budget !== null ? $budget - $spent : null;
            $pct      = $grandTotal > 0 ? round($spent / $grandTotal * 100, 2) : 0;

            return [
                'category'     => $row->category,
                'total_spent'  => $spent,
                'budget'       => $budget,
                'variance'     => $variance,
                'pct_of_total' => $pct,
            ];
        });

        return response()->json([
            'month'       => $month,
            'grand_total' => (float) $grandTotal,
            'data'        => $data,
        ]);
    }

    // ── GET /expenses/budgets ─────────────────────────────────────────────────

    public function indexBudgets(Request $request)
    {
        $request->validate(['month' => 'required|date_format:Y-m']);

        $user     = $request->user();
        $month    = $request->input('month');
        $branchId = $request->input('branch_id');

        [$year, $mon] = explode('-', $month);
        $monthDate = Carbon::create((int) $year, (int) $mon, 1)->toDateString();

        $budgets = ExpenseBudget::whereDate('month', $monthDate)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        // Attach actuals
        $start = $monthDate;
        $end   = Carbon::create((int) $year, (int) $mon, 1)->endOfMonth()->toDateString();

        $actuals = Expense::where('status', Expense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$start, $end])
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->selectRaw('category, SUM(amount) as total_spent')
            ->groupBy('category')
            ->pluck('total_spent', 'category');

        $result = $budgets->map(fn($b) => [
            'category'      => $b->category,
            'budget_amount' => (float) $b->budget_amount,
            'actual_spent'  => (float) ($actuals[$b->category] ?? 0),
            'variance'      => (float) $b->budget_amount - (float) ($actuals[$b->category] ?? 0),
        ]);

        return response()->json(['month' => $month, 'data' => $result]);
    }

    // ── POST /expenses/budgets ────────────────────────────────────────────────

    public function storeBudget(Request $request)
    {
        $validated = $request->validate([
            'category'      => 'required|string|max:100',
            'month'         => 'required|date_format:Y-m',
            'budget_amount' => 'required|numeric|min:0',
            'branch_id'     => 'nullable|integer|exists:warehouses,id',
        ]);

        [$year, $mon] = explode('-', $validated['month']);
        $monthDate = Carbon::create((int) $year, (int) $mon, 1)->toDateString();

        $budget = ExpenseBudget::updateOrCreate(
            [
                'tenant_id' => $request->user()->tenant_id,
                'branch_id' => $validated['branch_id'] ?? null,
                'category'  => $validated['category'],
                'month'     => $monthDate,
            ],
            ['budget_amount' => $validated['budget_amount']]
        );

        return response()->json(['budget' => $budget], 201);
    }

    // ── POST /expenses ────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category'     => 'required|string|max:100',
            'description'  => 'required|string',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'branch_id'    => 'nullable|integer|exists:warehouses,id',
            'notes'        => 'nullable|string',
        ]);

        $user      = $request->user();
        $threshold = (int) data_get($user->tenant?->config, 'expense_approval_threshold', 50000);
        $status    = ((float) $validated['amount'] > $threshold)
            ? Expense::STATUS_PENDING_APPROVAL
            : Expense::STATUS_APPROVED;

        $expense = null;

        DB::transaction(function () use ($validated, $user, $status, &$expense) {
            $expense = Expense::create(array_merge($validated, [
                'created_by' => $user->id,
                'status'     => $status,
            ]));

            ActivityLog::create([
                'user_id'    => $user->id,
                'action'     => 'created',
                'model_type' => Expense::class,
                'model_id'   => $expense->id,
                'new_values' => ['status' => $status, 'amount' => $expense->amount],
            ]);
        });

        // Notify managers if approval required (Hard Rule §3 — queued notification)
        if ($status === Expense::STATUS_PENDING_APPROVAL) {
            $this->notifyManagers($expense);
        }

        return response()->json(['expense' => $expense], 201);
    }

    // ── GET /expenses/{expense} ───────────────────────────────────────────────

    public function show(Expense $expense)
    {
        $expense->load(['createdBy', 'approvedBy', 'branch']);
        return view('expenses.show', compact('expense'));
    }

    // ── GET /expenses/{expense}/export-pdf ────────────────────────────────────

    public function exportPdf(Expense $expense)
    {
        $expense->load(['createdBy', 'approvedBy', 'branch']);
        $pdf = Pdf::loadView('expenses.export-pdf', compact('expense'));
        return $pdf->download("expense-{$expense->id}.pdf");
    }

    // ── GET /expenses/{expense}/export-excel ──────────────────────────────────

    public function exportExcel(Expense $expense)
    {
        $expense->load(['createdBy', 'approvedBy', 'branch']);
        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "expense-{$expense->id}.xlsx";
        $writer   = new XlsxWriter();
        $writer->openToFile($filename);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Expense');

        $headers = ['Field', 'Value'];
        $writer->addRow(Row::fromValues($headers));

        $rows = [
            ['Expense ID',         $expense->id],
            ['Category',           $expense->category],
            ['Description',        $expense->description],
            ['Amount (TZS)',       number_format($expense->amount, 2)],
            ['Expense Date',       $expense->expense_date],
            ['Status',             $expense->status],
            ['Branch',             $expense->branch?->name ?? '—'],
            ['Created By',         $expense->createdBy?->name ?? '—'],
            ['Submitted At',       $expense->submitted_at?->format('Y-m-d H:i') ?? '—'],
            ['Approved By',        $expense->approvedBy?->name ?? '—'],
            ['Approved At',        $expense->approved_at?->format('Y-m-d H:i') ?? '—'],
            ['Rejection Reason',   ($expense->status === Expense::STATUS_REJECTED ? $expense->notes : '—')],
            ['Notes',              $expense->notes ?? '—'],
            ['Receipt Attached',   $expense->receipt_path ? 'Yes' : 'No'],
            ['Created At',         $expense->created_at->format('Y-m-d H:i')],
            ['Updated At',         $expense->updated_at->format('Y-m-d H:i')],
        ];

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return response()->download($filename, "expense-{$expense->id}.xlsx")->deleteFileAfterSend();
    }

    // ── PUT /expenses/{expense} ───────────────────────────────────────────────

    public function update(Request $request, Expense $expense)
    {
        if ($expense->status !== Expense::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft expenses can be edited.'], 422);
        }

        $validated = $request->validate([
            'category'     => 'required|string|max:100',
            'description'  => 'required|string',
            'amount'       => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'branch_id'    => 'nullable|integer|exists:warehouses,id',
            'notes'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $expense, $request) {
            $old = $expense->only(['category', 'amount', 'status']);
            $expense->update($validated);

            ActivityLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'updated',
                'model_type' => Expense::class,
                'model_id'   => $expense->id,
                'old_values' => $old,
                'new_values' => $expense->only(['category', 'amount']),
            ]);
        });

        return response()->json(['expense' => $expense->fresh()]);
    }

    // ── POST /expenses/{expense}/submit ──────────────────────────────────────

    public function submit(Expense $expense)
    {
        if ($expense->status !== Expense::STATUS_DRAFT) {
            return response()->json(['message' => 'Only draft expenses can be submitted.'], 422);
        }

        $expense->update(['status' => Expense::STATUS_PENDING_APPROVAL]);
        $this->notifyManagers($expense);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'submitted',
            'model_type' => Expense::class,
            'model_id'   => $expense->id,
            'new_values' => ['status' => Expense::STATUS_PENDING_APPROVAL],
        ]);

        return response()->json(['expense' => $expense->fresh()]);
    }

    // ── POST /expenses/{expense}/approve ─────────────────────────────────────

    public function approve(Expense $expense)
    {
        if ($expense->status !== Expense::STATUS_PENDING_APPROVAL) {
            return response()->json(['message' => 'Expense must be pending approval.'], 422);
        }

        DB::transaction(function () use ($expense) {
            $expense->update([
                'status'      => Expense::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'approved',
                'model_type' => Expense::class,
                'model_id'   => $expense->id,
                'new_values' => ['status' => Expense::STATUS_APPROVED, 'approved_by' => auth()->id()],
            ]);

            ExpenseApproved::dispatch($expense);
        });

        return response()->json(['expense' => $expense->fresh()]);
    }

    // ── POST /expenses/{expense}/reject ──────────────────────────────────────

    public function reject(Request $request, Expense $expense)
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);

        if ($expense->status !== Expense::STATUS_PENDING_APPROVAL) {
            return response()->json(['message' => 'Expense must be pending approval to reject.'], 422);
        }

        DB::transaction(function () use ($validated, $expense, $request) {
            $expense->update([
                'status' => Expense::STATUS_REJECTED,
                'notes'  => $validated['reason'],
            ]);

            ActivityLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'rejected',
                'model_type' => Expense::class,
                'model_id'   => $expense->id,
                'new_values' => ['status' => Expense::STATUS_REJECTED, 'reason' => $validated['reason']],
            ]);
        });

        // Notify creator (Hard Rule §3 — queued notification)
        $expense->createdBy?->notify(new ExpenseRejectedNotification($expense, $validated['reason']));

        return response()->json(['expense' => $expense->fresh()]);
    }

    // ── POST /expenses/{expense}/receipt ─────────────────────────────────────

    public function uploadReceipt(Request $request, Expense $expense)
    {
        $request->validate([
            'receipt' => 'required|file|max:5120',   // 5 MB hard limit
        ]);

        $file = $request->file('receipt');

        // Server-side MIME validation — no extension-only check (spec requirement)
        $mimeType = $file->getMimeType();
        $allowed  = ['image/jpeg', 'image/png', 'application/pdf'];

        if (!in_array($mimeType, $allowed, true)) {
            return response()->json([
                'message' => 'Invalid file type. Allowed: JPEG, PNG, PDF.',
            ], 422);
        }

        $ext  = match ($mimeType) {
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'application/pdf' => 'pdf',
            default           => 'bin',
        };

        $tenantId = $request->user()->tenant_id ?? $expense->tenant_id;
        $dir      = "{$tenantId}/expenses/" . now()->year;
        $filename = Str::uuid() . '.' . $ext;

        $path = $file->storeAs($dir, $filename, 'local');

        // Remove old receipt if exists
        if ($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path)) {
            Storage::disk('local')->delete($expense->receipt_path);
        }

        $expense->update(['receipt_path' => $path]);

        return response()->json([
            'message'      => 'Receipt uploaded.',
            'receipt_path' => $path,
        ]);
    }

    // ── Private: notify managers ──────────────────────────────────────────────

    private function notifyManagers(Expense $expense): void
    {
        $tenantId = $expense->tenant_id;
        if (!$tenantId) {
            return;
        }

        // Find all users in this tenant who have expenses.manage permission
        $managers = User::permission('expenses.manage')
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new ExpenseApprovalNotification($expense));
        }
    }
}
