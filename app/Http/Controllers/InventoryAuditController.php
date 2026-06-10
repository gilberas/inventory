<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryAudit;
use App\Models\InventoryAuditItem;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryAuditController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    // ── GET /audits ───────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $audits = InventoryAudit::with(['warehouse', 'initiatedBy'])
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->status,    fn ($q) => $q->where('status', $request->status))
            ->when($request->date,      fn ($q) => $q->whereDate('audit_date', $request->date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches   = Branch::where('tenant_id', auth()->user()->tenant_id)->where('is_active', true)->get();
        $warehouses = Warehouse::active()->get();
        $statuses   = [
            InventoryAudit::STATUS_INITIATED => 'Initiated',
            InventoryAudit::STATUS_COUNTING  => 'Counting',
            InventoryAudit::STATUS_COMPLETED => 'Completed',
            InventoryAudit::STATUS_POSTED    => 'Posted',
        ];

        return view('audits.index', compact('audits', 'branches', 'warehouses', 'statuses'));
    }

    // ── GET /audits/{audit} ───────────────────────────────────────────────────

    public function show(InventoryAudit $audit)
    {
        $audit->load(['warehouse.branch', 'initiatedBy', 'approvedBy', 'items.product.unit']);

        return view('audits.show', compact('audit'));
    }

    // ── POST /audits  — initiate a new audit for a warehouse ─────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'audit_date'   => 'required|date',
            'notes'        => 'nullable|string|max:1000',
        ]);

        // Block if another active (non-posted) audit already exists for this warehouse
        $existing = InventoryAudit::where('warehouse_id', $validated['warehouse_id'])
            ->whereIn('status', [
                InventoryAudit::STATUS_INITIATED,
                InventoryAudit::STATUS_COUNTING,
                InventoryAudit::STATUS_COMPLETED,
            ])
            ->first();

        if ($existing) {
            return back()->withErrors([
                'warehouse_id' => "Warehouse already has an active audit (#{$existing->id}). Post it before starting a new one.",
            ]);
        }

        $audit = DB::transaction(function () use ($validated, $request) {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

            $audit = InventoryAudit::create([
                'warehouse_id' => $validated['warehouse_id'],
                'branch_id'    => $warehouse->branch_id,
                'status'       => InventoryAudit::STATUS_INITIATED,
                'initiated_by' => auth()->id(),
                'audit_date'   => $validated['audit_date'],
            ]);

            // Snapshot: copy current inventory quantities → system_qty for ALL products in warehouse
            $inventoryRows = Inventory::where('warehouse_id', $validated['warehouse_id'])
                ->where('quantity', '>', 0)
                ->get(['product_id', 'quantity']);

            foreach ($inventoryRows as $row) {
                InventoryAuditItem::create([
                    'audit_id'   => $audit->id,
                    'product_id' => $row->product_id,
                    'system_qty' => (float) $row->quantity,
                    'variance'   => 0,
                ]);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'audit_initiated',
                'model_type' => InventoryAudit::class,
                'model_id'   => $audit->id,
                'new_values' => [
                    'warehouse_id' => $validated['warehouse_id'],
                    'item_count'   => $inventoryRows->count(),
                    'audit_date'   => $validated['audit_date'],
                ],
                'ip_address' => $request->ip(),
            ]);

            return $audit;
        });

        return redirect()->route('audits.show', $audit)
            ->with('success', "Audit #{$audit->id} initiated with {$audit->items()->count()} products snapshotted.");
    }

    // ── GET /audits/{audit}/sheet — count sheet (system_qty hidden) ───────────

    public function sheet(InventoryAudit $audit)
    {
        abort_if($audit->isPosted(), 422, 'Audit is already posted.');

        $audit->load(['warehouse', 'items.product.unit']);

        // Return items with system_qty masked — storekeeper must count without bias
        $items = $audit->items->map(fn (InventoryAuditItem $item) => [
            'id'           => $item->id,
            'product_id'   => $item->product_id,
            'product_name' => $item->product->name,
            'sku'          => $item->product->sku,
            'unit'         => $item->product->unit?->abbreviation,
            'system_qty'   => null,                 // hidden
            'physical_qty' => $item->physical_qty,
            'notes'        => $item->notes,
        ]);

        return view('audits.sheet', compact('audit', 'items'));
    }

    // ── POST /audits/{audit}/counts — submit physical counts ──────────────────

    public function counts(Request $request, InventoryAudit $audit)
    {
        abort_if($audit->isPosted(), 422, 'Audit is already posted.');

        $request->validate([
            'items'                  => 'required|array|min:1',
            'items.*.id'             => 'required|integer|exists:inventory_audit_items,id',
            'items.*.physical_qty'   => 'required|numeric|min:0',
            'items.*.notes'          => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($request, $audit) {
            $audit->load('items');
            $itemMap = $audit->items->keyBy('id');

            foreach ($request->items as $data) {
                $item = $itemMap->get($data['id']);
                abort_if(! $item || $item->audit_id !== $audit->id, 422, 'Invalid audit item.');

                $physical  = (float) $data['physical_qty'];
                $variance  = $physical - (float) $item->system_qty;

                $item->update([
                    'physical_qty' => $physical,
                    'variance'     => $variance,
                    'notes'        => $data['notes'] ?? null,
                ]);
            }

            // Advance status: initiated → counting; counting → completed when all counted
            $audit->load('items');
            $newStatus = $audit->allItemsCounted()
                ? InventoryAudit::STATUS_COMPLETED
                : InventoryAudit::STATUS_COUNTING;

            $audit->update(['status' => $newStatus]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'audit_counts_submitted',
                'model_type' => InventoryAudit::class,
                'model_id'   => $audit->id,
                'new_values' => ['status' => $newStatus, 'items_counted' => count($request->items)],
                'ip_address' => request()->ip(),
            ]);
        });

        return redirect()->route('audits.show', $audit)
            ->with('success', 'Physical counts saved.');
    }

    // ── GET /audits/{audit}/variance — variance report ────────────────────────

    public function variance(InventoryAudit $audit)
    {
        $audit->load(['warehouse', 'items.product']);

        $overages  = [];
        $shortages = [];
        $matches   = [];

        foreach ($audit->items as $item) {
            if ($item->physical_qty === null) continue;

            $variance     = (float) $item->variance;
            $costPrice    = (float) ($item->product->cost_price ?? 0);
            $valueImpact  = $variance * $costPrice;

            $row = [
                'id'           => $item->id,
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'sku'          => $item->product->sku,
                'system_qty'   => (float) $item->system_qty,
                'physical_qty' => (float) $item->physical_qty,
                'variance'     => $variance,
                'value_impact' => $valueImpact,
            ];

            if ($variance > 0)       $overages[]  = $row;
            elseif ($variance < 0)   $shortages[] = $row;
            else                     $matches[]   = $row;
        }

        $summary = [
            'total_overages_value'  => array_sum(array_column($overages,  'value_impact')),
            'total_shortages_value' => array_sum(array_column($shortages, 'value_impact')),
            'net_variance_value'    => array_sum(array_column($overages,  'value_impact'))
                                     + array_sum(array_column($shortages, 'value_impact')),
        ];

        return view('audits.variance', compact('audit', 'overages', 'shortages', 'matches', 'summary'));
    }

    // ── POST /audits/{audit}/post — approve and apply adjustments ─────────────

    public function post(Request $request, InventoryAudit $audit)
    {
        abort_if($audit->isPosted(), 422, 'Audit is already posted.');
        abort_if(
            ! in_array($audit->status, [InventoryAudit::STATUS_COUNTING, InventoryAudit::STATUS_COMPLETED]),
            422,
            'Audit must be in counting or completed status before posting.'
        );

        DB::transaction(function () use ($request, $audit) {
            $audit->load('items.product');

            foreach ($audit->items as $item) {
                if ($item->physical_qty === null) continue;

                $variance = (float) $item->variance;

                if (abs($variance) > 0.0001) {
                    // Apply inventory adjustment for the variance
                    // Skip the audit lock here — we ARE the audit posting
                    $inv = Inventory::firstOrCreate(
                        ['product_id' => $item->product_id, 'warehouse_id' => $audit->warehouse_id],
                        ['quantity' => 0, 'valuation_method' => 'weighted_avg', 'unit_cost' => 0]
                    );

                    $newQty = max(0.0, (float) $inv->quantity + $variance);
                    $inv->update(['quantity' => $newQty, 'last_updated' => now()]);

                    \App\Models\InventoryMovement::create([
                        'product_id'     => $item->product_id,
                        'warehouse_id'   => $audit->warehouse_id,
                        'type'           => 'adjustment',
                        'qty'            => $variance,
                        'balance_after'  => $newQty,
                        'unit_cost'      => null,
                        'reference_type' => 'audit',
                        'reference_id'   => $audit->id,
                        'user_id'        => auth()->id(),
                        'notes'          => "Audit #{$audit->id} variance adjustment",
                        'created_at'     => now(),
                    ]);
                }
            }

            $audit->update([
                'status'      => InventoryAudit::STATUS_POSTED,
                'approved_by' => auth()->id(),
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'audit_posted',
                'model_type' => InventoryAudit::class,
                'model_id'   => $audit->id,
                'new_values' => [
                    'status'      => InventoryAudit::STATUS_POSTED,
                    'approved_by' => auth()->id(),
                    'items'       => $audit->items->count(),
                ],
                'ip_address' => request()->ip(),
            ]);
        });

        return redirect()->route('audits.show', $audit)
            ->with('success', "Audit #{$audit->id} posted. Inventory adjusted for all variances.");
    }
}
