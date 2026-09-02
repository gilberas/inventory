<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\BranchTransfer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\StockTransferDiscrepancy;
use App\Notifications\StockTransferStatusChanged;
use App\Notifications\StockTransferSubmitted;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    // ── GET /transfers ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $transfers = BranchTransfer::with(['fromBranch', 'toBranch', 'requestedBy'])
            ->withCount('items')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from_branch_id, fn ($q) => $q->where('from_branch_id', $request->from_branch_id))
            ->when($request->to_branch_id, fn ($q) => $q->where('to_branch_id', $request->to_branch_id))
            ->when($request->date, fn ($q) => $q->whereDate('created_at', $request->date))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches = Branch::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $statuses = [
            BranchTransfer::STATUS_PENDING => 'Pending',
            BranchTransfer::STATUS_APPROVED => 'Approved',
            BranchTransfer::STATUS_DISPATCHED => 'Dispatched',
            BranchTransfer::STATUS_RECEIVED => 'Received',
            BranchTransfer::STATUS_REJECTED => 'Rejected',
        ];

        return view('transfers.index', compact('transfers', 'branches', 'statuses'));
    }

    // ── GET /transfers/create ─────────────────────────────────────────────────

    public function create()
    {
        $branches = Branch::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $products = Product::active()->with('unit')->orderBy('name')->get();

        return view('transfers.create', compact('branches', 'products'));
    }

    // ── GET /transfers/quick-create ───────────────────────────────────────────
    // Streamlined 3-step flow for storekeepers: product + destination + qty → review → submit

    public function quickCreate()
    {
        $user = auth()->user();

        $branches = Branch::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->where('id', '!=', $user->branch_id)
            ->orderBy('name')
            ->get();

        $products = Product::active()->with('unit')->orderBy('name')->get();

        return view('transfers.quick-create', compact('branches', 'products'));
    }

    // ── POST /transfers ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        // Auto-set from_branch_id from the authenticated user (BM-2)
        $fromBranchId = (int) (auth()->user()->branch_id ?? $request->input('from_branch_id'));

        $validated = $request->validate([
            'from_branch_id' => 'nullable|integer|exists:branches,id',
            'to_branch_id' => 'required|integer|exists:branches,id',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.qty_requested' => 'required|numeric|min:0.01',
        ]);

        if ($fromBranchId === (int) $validated['to_branch_id']) {
            throw ValidationException::withMessages([
                'to_branch_id' => ['Source and destination branches must differ.'],
            ]);
        }

        $transfer = DB::transaction(function () use ($validated, $fromBranchId) {
            $transfer = BranchTransfer::create([
                'from_branch_id' => $fromBranchId,
                'to_branch_id' => $validated['to_branch_id'],
                'requested_by' => auth()->id(),
                'status' => BranchTransfer::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'qty_requested' => $item['qty_requested'],
                ]);
            }

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'created',
                'model_type' => BranchTransfer::class,
                'model_id' => $transfer->id,
                'new_values' => ['status' => BranchTransfer::STATUS_PENDING, 'items' => count($validated['items'])],
                'ip_address' => request()->ip(),
            ]);

            return $transfer;
        });

        // Notify destination branch manager (Hard Rule §3: queued)
        $manager = $this->branchManager($transfer->to_branch_id);
        if ($manager) {
            $manager->notify(new StockTransferSubmitted($transfer->load(['fromBranch', 'toBranch', 'items'])));
        }

        return redirect()->route('transfers.show', $transfer)
            ->with('success', 'Transfer request created and destination branch notified.');
    }

    // ── GET /transfers/{transfer} ─────────────────────────────────────────────

    public function show(BranchTransfer $transfer)
    {
        $transfer->load(['fromBranch', 'toBranch', 'requestedBy', 'approvedBy', 'items.product.unit']);

        return view('transfers.show', compact('transfer'));
    }

    // ── POST /transfers/{transfer}/approve ────────────────────────────────────

    public function approve(BranchTransfer $transfer)
    {
        abort_if(! $transfer->isPending(), 422, 'Only pending transfers can be approved.');

        DB::transaction(function () use ($transfer) {
            $transfer->update([
                'status' => BranchTransfer::STATUS_APPROVED,
                'approved_by' => auth()->id(),
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'approved',
                'model_type' => BranchTransfer::class,
                'model_id' => $transfer->id,
                'new_values' => ['status' => BranchTransfer::STATUS_APPROVED],
                'ip_address' => request()->ip(),
            ]);
        });

        // Notify source branch manager
        $manager = $this->branchManager($transfer->from_branch_id);
        if ($manager) {
            $manager->notify(new StockTransferStatusChanged($transfer->load(['fromBranch', 'toBranch']), 'approved'));
        }

        return back()->with('success', 'Transfer approved. Source branch has been notified.');
    }

    // ── POST /transfers/{transfer}/reject ─────────────────────────────────────

    public function reject(Request $request, BranchTransfer $transfer)
    {
        $request->validate(['reason' => 'required|string|max:1000']);

        abort_if(! $transfer->isPending(), 422, 'Only pending transfers can be rejected.');

        DB::transaction(function () use ($request, $transfer) {
            $existingNotes = $transfer->notes ? $transfer->notes."\n" : '';
            $transfer->update([
                'status' => BranchTransfer::STATUS_REJECTED,
                'notes' => $existingNotes."[Rejected: {$request->reason}]",
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'rejected',
                'model_type' => BranchTransfer::class,
                'model_id' => $transfer->id,
                'new_values' => ['status' => BranchTransfer::STATUS_REJECTED, 'reason' => $request->reason],
                'ip_address' => request()->ip(),
            ]);
        });

        // Notify source branch manager
        $manager = $this->branchManager($transfer->from_branch_id);
        if ($manager) {
            $manager->notify(new StockTransferStatusChanged($transfer->load(['fromBranch', 'toBranch']), 'rejected'));
        }

        return back()->with('success', 'Transfer rejected. Source branch has been notified.');
    }

    // ── POST /transfers/{transfer}/dispatch ───────────────────────────────────
    // Inventory decrements at DISPATCH from source warehouse (not at request time)

    public function dispatch(Request $request, BranchTransfer $transfer)
    {
        abort_if(! $transfer->isApproved(), 422, 'Only approved transfers can be dispatched.');

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:branch_transfer_items,id',
            'items.*.qty_dispatched' => 'required|numeric|min:0.01',
        ]);

        $fromWarehouse = $this->branchWarehouse($transfer->from_branch_id);

        try {
            DB::transaction(function () use ($request, $transfer, $fromWarehouse) {
                $transfer->load('items');
                $itemMap = $transfer->items->keyBy('id');

                foreach ($request->items as $itemData) {
                    $item = $itemMap->get($itemData['id']);
                    abort_if(! $item || $item->transfer_id !== $transfer->id, 422, 'Invalid item.');

                    $item->update(['qty_dispatched' => $itemData['qty_dispatched']]);

                    // Critical: source inventory decrements at DISPATCH
                    $this->inventoryService->stockOut(
                        productId: $item->product_id,
                        warehouseId: $fromWarehouse->id,
                        qty: (float) $itemData['qty_dispatched'],
                        refType: 'transfer_out',
                        refId: $transfer->id,
                        userId: auth()->id(),
                        notes: "Dispatched — branch transfer #{$transfer->id}",
                    );
                }

                $transfer->update([
                    'status' => BranchTransfer::STATUS_DISPATCHED,
                    'dispatched_at' => now(),
                ]);

                ActivityLog::create([
                    'user_id' => auth()->id(),
                    'action' => 'dispatched',
                    'model_type' => BranchTransfer::class,
                    'model_id' => $transfer->id,
                    'new_values' => ['status' => BranchTransfer::STATUS_DISPATCHED, 'dispatched_at' => now()],
                    'ip_address' => request()->ip(),
                ]);
            });
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        // Notify destination branch manager
        $manager = $this->branchManager($transfer->to_branch_id);
        if ($manager) {
            $manager->notify(new StockTransferStatusChanged($transfer->load(['fromBranch', 'toBranch']), 'dispatched'));
        }

        return back()->with('success', 'Transfer dispatched. Stock deducted from source branch.');
    }

    // ── POST /transfers/{transfer}/receive ────────────────────────────────────
    // Inventory increments at RECEIVE at destination warehouse (not at dispatch time)

    public function receive(Request $request, BranchTransfer $transfer)
    {
        abort_if(! $transfer->isDispatched(), 422, 'Only dispatched transfers can be received.');

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:branch_transfer_items,id',
            'items.*.qty_received' => 'required|numeric|min:0',
        ]);

        $toWarehouse = $this->branchWarehouse($transfer->to_branch_id);

        DB::transaction(function () use ($request, $transfer, $toWarehouse) {
            $transfer->load(['items.product']);
            $itemMap = $transfer->items->keyBy('id');

            foreach ($request->items as $itemData) {
                $item = $itemMap->get($itemData['id']);
                abort_if(! $item || $item->transfer_id !== $transfer->id, 422, 'Invalid item.');

                $item->update(['qty_received' => $itemData['qty_received']]);

                if ((float) $itemData['qty_received'] > 0) {
                    // Critical: destination inventory increments at RECEIVE
                    $this->inventoryService->stockIn(
                        productId: $item->product_id,
                        warehouseId: $toWarehouse->id,
                        qty: (float) $itemData['qty_received'],
                        unitCost: (float) ($item->product->cost_price ?? 0),
                        refType: 'transfer_in',
                        refId: $transfer->id,
                        userId: auth()->id(),
                        notes: "Received — branch transfer #{$transfer->id}",
                    );
                }
            }

            $transfer->update([
                'status' => BranchTransfer::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'received',
                'model_type' => BranchTransfer::class,
                'model_id' => $transfer->id,
                'new_values' => ['status' => BranchTransfer::STATUS_RECEIVED, 'received_at' => now()],
                'ip_address' => request()->ip(),
            ]);
        });

        // Reload after transaction to get fresh items
        $transfer->load(['items', 'fromBranch', 'toBranch']);

        // Discrepancy: flag and notify both branch managers
        if ($transfer->hasDiscrepancy()) {
            foreach ([$transfer->from_branch_id, $transfer->to_branch_id] as $branchId) {
                $manager = $this->branchManager($branchId);
                if ($manager) {
                    $manager->notify(new StockTransferDiscrepancy($transfer));
                }
            }
        }

        // Notify source branch that goods were received
        $sourceManager = $this->branchManager($transfer->from_branch_id);
        if ($sourceManager) {
            $sourceManager->notify(new StockTransferStatusChanged($transfer, 'received'));
        }

        $msg = $transfer->hasDiscrepancy()
            ? 'Transfer received with quantity discrepancies. Both branches have been notified.'
            : 'Transfer received. Stock added to destination branch.';

        return back()->with('success', $msg);
    }

    // ── DELETE /transfers/{transfer} ──────────────────────────────────────────

    public function destroy(BranchTransfer $transfer)
    {
        abort_if(! $transfer->isPending(), 422, 'Only pending transfers can be deleted.');

        $transfer->delete();

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer request deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function branchWarehouse(int $branchId): Warehouse
    {
        return Warehouse::where('branch_id', $branchId)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first()
            ?? Warehouse::where('branch_id', $branchId)
                ->where('is_active', true)
                ->firstOrFail();
    }

    private function branchManager(int $branchId): ?User
    {
        $warehouse = Warehouse::where('branch_id', $branchId)
            ->where('is_default', true)
            ->first();

        return $warehouse?->manager_id ? User::find($warehouse->manager_id) : null;
    }
}
