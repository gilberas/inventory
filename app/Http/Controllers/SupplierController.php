<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    // ── GET /suppliers ────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Supplier::withCount('purchaseOrders');

        // Default: active only. Pass ?status=inactive or ?status=all to override.
        $status = $request->input('status', Supplier::STATUS_ACTIVE);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        $suppliers = $query->latest()->paginate(15)->withQueryString();

        return response()->json(['data' => $suppliers]);
    }

    // ── POST /suppliers ───────────────────────────────────────

    public function create()
    {
        return response()->json(['status_options' => [Supplier::STATUS_ACTIVE, Supplier::STATUS_INACTIVE]]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'tin'            => 'nullable|string|max:50',
            'status'         => 'nullable|in:active,inactive',
        ]);

        $supplier = null;
        DB::transaction(function () use ($validated, &$supplier) {
            $supplier = Supplier::create($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Supplier::class,
                'model_id'   => $supplier->id,
                'new_values' => ['name' => $supplier->name, 'status' => $supplier->status],
            ]);
        });

        return redirect()->back()->with('success', "Supplier {$supplier->name} created.");
    }

    // ── GET /suppliers/{supplier} — profile + metrics + aging ─

    public function show(Supplier $supplier)
    {
        $supplier->load(['purchaseOrders' => fn($q) => $q->latest()->limit(10)]);

        $metrics = $this->computePerformanceMetrics($supplier);
        $aging   = $supplier->getAgingAnalysis();

        return response()->json([
            'supplier' => $supplier,
            'metrics'  => $metrics,
            'aging'    => $aging,
        ]);
    }

    // ── PUT /suppliers/{supplier} ─────────────────────────────

    public function edit(Supplier $supplier)
    {
        return response()->json(['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:30',
            'email'          => 'nullable|email|max:255',
            'address'        => 'nullable|string',
            'tin'            => 'nullable|string|max:50',
            'status'         => 'nullable|in:active,inactive',
        ]);

        DB::transaction(function () use ($validated, $supplier) {
            $old = $supplier->only(['name', 'status']);
            $supplier->update($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Supplier::class,
                'model_id'   => $supplier->id,
                'old_values' => $old,
                'new_values' => $supplier->only(['name', 'status']),
            ]);
        });

        return redirect()->back()->with('success', 'Supplier updated.');
    }

    // ── DELETE /suppliers/{supplier} — set inactive, never hard-delete ─────────

    public function destroy(Supplier $supplier)
    {
        DB::transaction(function () use ($supplier) {
            $supplier->update(['status' => Supplier::STATUS_INACTIVE]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deactivated',
                'model_type' => Supplier::class,
                'model_id'   => $supplier->id,
                'new_values' => ['status' => Supplier::STATUS_INACTIVE],
            ]);
        });

        return redirect()->back()->with('success', "{$supplier->name} marked as inactive.");
    }

    // ── GET /suppliers/{supplier}/history ─────────────────────

    public function history(Request $request, Supplier $supplier)
    {
        $request->validate([
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
            'status' => 'nullable|string',
        ]);

        $query = $supplier->purchaseOrders()
            ->with(['warehouse', 'items.product'])
            ->latest('order_date');

        if ($request->filled('from')) {
            $query->whereDate('order_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('order_date', '<=', $request->to);
        }
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->status));
        }

        $history = $query->paginate(15)->withQueryString();

        return response()->json([
            'supplier' => $supplier->only(['id', 'name', 'code']),
            'history'  => $history,
        ]);
    }

    // ── GET /suppliers/{supplier}/aging ───────────────────────

    public function aging(Supplier $supplier)
    {
        $aging = $supplier->getAgingAnalysis();

        $outstanding = $supplier->invoices()
            ->whereNotIn('status', [SupplierInvoice::STATUS_PAID])
            ->with('grn')
            ->orderBy('due_date')
            ->get()
            ->map(function ($invoice) {
                $dueDate     = \Carbon\Carbon::parse($invoice->due_date);
                $today       = \Carbon\Carbon::today();
                $daysOverdue = $dueDate->gte($today) ? 0 : (int) $today->diffInDays($dueDate);
                return array_merge($invoice->toArray(), ['days_overdue' => $daysOverdue]);
            });

        return response()->json([
            'supplier'      => $supplier->only(['id', 'name', 'balance']),
            'aging_summary' => $aging,
            'outstanding'   => $outstanding,
        ]);
    }

    // ── Performance metrics (private helper) ──────────────────

    private function computePerformanceMetrics(Supplier $supplier): array
    {
        $poIds = $supplier->purchaseOrders()->pluck('id');

        if ($poIds->isEmpty()) {
            return $this->emptyMetrics();
        }

        $grns = GoodsReceivedNote::whereIn('purchase_order_id', $poIds)
            ->where('status', GoodsReceivedNote::STATUS_CONFIRMED)
            ->with(['purchaseOrder:id,expected_date,created_at'])
            ->get();

        $totalGrns = $grns->count();

        if ($totalGrns === 0) {
            return $this->emptyMetrics();
        }

        // On-time: received date (normalised to midnight) ≤ expected_date midnight.
        // Using startOfDay() removes the time component before comparison so a
        // datetime received_at stored as "2026-06-01 00:00:00" compares correctly
        // against an expected_date stored as a date "2026-06-05".
        $onTimeCount = $grns->filter(function ($grn) {
            if (!$grn->purchaseOrder?->expected_date || !$grn->received_at) {
                return false;
            }
            $received = \Carbon\Carbon::parse($grn->received_at)->startOfDay();
            $expected = \Carbon\Carbon::parse($grn->purchaseOrder->expected_date)->startOfDay();
            return $received->lte($expected);
        })->count();

        $onTimeRate = round($onTimeCount / $totalGrns * 100, 2);

        // Average lead time in whole days (PO created_at midnight → GRN received_at midnight).
        // Normalising both to startOfDay() prevents partial-day fractional results when
        // created_at has a non-zero time component (e.g. "2026-05-26 12:00:00").
        $leadTimes = $grns
            ->filter(fn($grn) => $grn->received_at && $grn->purchaseOrder?->created_at)
            ->map(function ($grn) {
                $received = \Carbon\Carbon::parse($grn->received_at)->startOfDay();
                $created  = \Carbon\Carbon::parse($grn->purchaseOrder->created_at)->startOfDay();
                return (float) abs($received->diffInDays($created));
            });

        $avgLeadTime = $leadTimes->isNotEmpty()
            ? round($leadTimes->average(), 1)
            : 0.0;

        // Return rate: purchase returns referencing any of these GRNs
        $returnCount = PurchaseReturn::whereIn('grn_id', $grns->pluck('id'))->count();
        $returnRate  = round($returnCount / $totalGrns * 100, 2);

        return [
            'total_grns'            => $totalGrns,
            'on_time_delivery_rate' => $onTimeRate,
            'average_lead_time'     => $avgLeadTime,
            'return_rate'           => $returnRate,
        ];
    }

    private function emptyMetrics(): array
    {
        return [
            'total_grns'            => 0,
            'on_time_delivery_rate' => 0.0,
            'average_lead_time'     => 0.0,
            'return_rate'           => 0.0,
        ];
    }
}
