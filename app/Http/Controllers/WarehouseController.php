<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    // ── Warehouse CRUD ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $warehouses = Warehouse::with('branch')
            ->withCount('locations')
            ->when($request->branch_id, fn ($q) => $q->where('branch_id', $request->branch_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses'));
    }

    public function create()
    {
        $managers = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('warehouses.create', compact('managers'));
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:255',
            'code'       => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses')->where('tenant_id', $tenantId),
            ],
            'address'    => 'nullable|string',
            'capacity'   => 'nullable|integer|min:1',
            'manager_id' => 'nullable|exists:users,id',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ]);

        $warehouse = DB::transaction(function () use ($validated, $request) {
            $warehouse = Warehouse::create($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Warehouse::class,
                'model_id'   => $warehouse->id,
                'new_values' => $warehouse->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $warehouse;
        });

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', "Warehouse '{$warehouse->name}' created.");
    }

    public function show(Warehouse $warehouse)
    {
        $warehouse->load(['branch', 'manager', 'locations']);

        $productCount = Inventory::where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->count();

        $totalQty = Inventory::where('warehouse_id', $warehouse->id)
            ->sum('quantity');

        $totalValue = $warehouse->total_stock_value;

        return view('warehouses.show', compact('warehouse', 'productCount', 'totalQty', 'totalValue'));
    }

    public function edit(Warehouse $warehouse)
    {
        $managers = User::where('tenant_id', auth()->user()->tenant_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('warehouses.edit', compact('warehouse', 'managers'));
    }

    public function update(Request $request, Warehouse $warehouse)
    {
        $tenantId = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'code'       => [
                'required', 'string', 'max:50',
                Rule::unique('warehouses')->where('tenant_id', $tenantId)->ignore($warehouse->id),
            ],
            'address'    => 'nullable|string',
            'capacity'   => 'nullable|integer|min:1',
            'manager_id' => 'nullable|exists:users,id',
            'is_default' => 'boolean',
            'is_active'  => 'boolean',
        ]);

        $old = $warehouse->toArray();

        DB::transaction(function () use ($warehouse, $validated, $request, $old) {
            $warehouse->update($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Warehouse::class,
                'model_id'   => $warehouse->id,
                'old_values' => $old,
                'new_values' => $warehouse->fresh()->toArray(),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('warehouses.show', $warehouse)
            ->with('success', 'Warehouse updated.');
    }

    public function destroy(Request $request, Warehouse $warehouse)
    {
        if ($warehouse->is_default) {
            return back()->withErrors(['warehouse' => 'Cannot delete the default warehouse.']);
        }

        if (Inventory::where('warehouse_id', $warehouse->id)->where('quantity', '>', 0)->exists()) {
            return back()->withErrors(['warehouse' => 'Cannot delete a warehouse with stock on hand.']);
        }

        DB::transaction(function () use ($warehouse, $request) {
            $old = $warehouse->toArray();
            $warehouse->delete();

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'model_type' => Warehouse::class,
                'model_id'   => $warehouse->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('warehouses.index')->with('success', 'Warehouse deleted.');
    }

    // ── Bin Locations ─────────────────────────────────────────────────────────

    public function storeLocation(Request $request, Warehouse $warehouse)
    {
        $validated = $request->validate([
            'aisle' => 'nullable|string|max:10',
            'shelf' => 'nullable|string|max:10',
            'bin'   => 'nullable|string|max:10',
        ]);

        $warehouse->locations()->create($validated);

        return back()->with('success', 'Location added.');
    }

    public function destroyLocation(Warehouse $warehouse, WarehouseLocation $location)
    {
        abort_if($location->warehouse_id !== $warehouse->id, 404);
        $location->delete();

        return back()->with('success', 'Location removed.');
    }

    // ── Intra-Branch Warehouse Transfers ──────────────────────────────────────

    public function indexTransfers(Request $request)
    {
        $transfers = WarehouseTransfer::with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('warehouses.transfers.index', compact('transfers'));
    }

    public function createTransfer()
    {
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name', 'branch_id']);
        return view('warehouses.transfers.create', compact('warehouses'));
    }

    public function storeTransfer(Request $request)
    {
        $validated = $request->validate([
            'from_warehouse_id'       => 'required|exists:warehouses,id',
            'to_warehouse_id'         => 'required|exists:warehouses,id|different:from_warehouse_id',
            'notes'                   => 'nullable|string|max:1000',
            'items'                   => 'required|array|min:1',
            'items.*.product_id'      => 'required|exists:products,id',
            'items.*.qty'             => 'required|numeric|min:0.0001',
            'items.*.unit_cost'       => 'nullable|numeric|min:0',
        ]);

        $transfer = DB::transaction(function () use ($validated, $request) {
            $transfer = WarehouseTransfer::create([
                'from_warehouse_id' => $validated['from_warehouse_id'],
                'to_warehouse_id'   => $validated['to_warehouse_id'],
                'status'            => WarehouseTransfer::STATUS_PENDING,
                'notes'             => $validated['notes'] ?? null,
                'requested_by'      => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'unit_cost'  => $item['unit_cost'] ?? null,
                ]);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => WarehouseTransfer::class,
                'model_id'   => $transfer->id,
                'new_values' => $transfer->load('items')->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $transfer;
        });

        return redirect()->route('warehouses.transfers.index')
            ->with('success', "Transfer #{$transfer->id} created.");
    }

    public function approveTransfer(Request $request, WarehouseTransfer $transfer)
    {
        abort_if($transfer->status !== WarehouseTransfer::STATUS_PENDING, 422,
            'Only pending transfers can be approved.');

        $transfer->update([
            'status'      => WarehouseTransfer::STATUS_APPROVED,
            'approved_by' => auth()->id(),
        ]);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'approved',
            'model_type' => WarehouseTransfer::class,
            'model_id'   => $transfer->id,
            'new_values' => ['status' => WarehouseTransfer::STATUS_APPROVED],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('warehouses.transfers.index')
            ->with('success', "Transfer #{$transfer->id} approved.");
    }

    public function dispatchTransfer(Request $request, WarehouseTransfer $transfer)
    {
        abort_if($transfer->status !== WarehouseTransfer::STATUS_APPROVED, 422,
            'Only approved transfers can be dispatched.');

        $transfer->load('items');

        try {
            DB::transaction(function () use ($transfer, $request) {
                foreach ($transfer->items as $item) {
                    $this->inventoryService->stockOut(
                        (int) $item->product_id,
                        (int) $transfer->from_warehouse_id,
                        (float) $item->qty,
                        'transfer_out',
                        $transfer->id,
                        auth()->id(),
                        "Transfer #{$transfer->id} dispatch"
                    );
                }

                $transfer->update([
                    'status'        => WarehouseTransfer::STATUS_DISPATCHED,
                    'dispatched_at' => now(),
                ]);

                ActivityLog::create([
                    'user_id'    => auth()->id(),
                    'action'     => 'dispatched',
                    'model_type' => WarehouseTransfer::class,
                    'model_id'   => $transfer->id,
                    'new_values' => ['status' => WarehouseTransfer::STATUS_DISPATCHED],
                    'ip_address' => $request->ip(),
                ]);
            });
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return redirect()->route('warehouses.transfers.index')
            ->with('success', "Transfer #{$transfer->id} dispatched.");
    }

    public function receiveTransfer(Request $request, WarehouseTransfer $transfer)
    {
        abort_if($transfer->status !== WarehouseTransfer::STATUS_DISPATCHED, 422,
            'Only dispatched transfers can be received.');

        $transfer->load('items');

        DB::transaction(function () use ($transfer, $request) {
            foreach ($transfer->items as $item) {
                $this->inventoryService->stockIn(
                    (int) $item->product_id,
                    (int) $transfer->to_warehouse_id,
                    (float) $item->qty,
                    (float) ($item->unit_cost ?? 0),
                    'transfer_in',
                    $transfer->id,
                    auth()->id(),
                    "Transfer #{$transfer->id} receive"
                );
            }

            $transfer->update([
                'status'      => WarehouseTransfer::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'received',
                'model_type' => WarehouseTransfer::class,
                'model_id'   => $transfer->id,
                'new_values' => ['status' => WarehouseTransfer::STATUS_RECEIVED],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('warehouses.transfers.index')
            ->with('success', "Transfer #{$transfer->id} received.");
    }

    // ── Stock Reports ─────────────────────────────────────────────────────────

    public function stockReport(Request $request, Warehouse $warehouse)
    {
        $stock = Inventory::with('product.category')
            ->where('warehouse_id', $warehouse->id)
            ->where('quantity', '>', 0)
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($stock);
        }

        return view('warehouses.reports.stock', compact('warehouse', 'stock'));
    }

    public function movementsReport(Request $request, Warehouse $warehouse)
    {
        $movements = InventoryMovement::with('product')
            ->where('warehouse_id', $warehouse->id)
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($movements);
        }

        return view('warehouses.reports.movements', compact('warehouse', 'movements'));
    }

    public function valuationReport(Request $request, Warehouse $warehouse)
    {
        $rows = DB::table('inventory')
            ->join('products', 'products.id', '=', 'inventory.product_id')
            ->join('product_categories', 'product_categories.id', '=', 'products.category_id')
            ->where('inventory.warehouse_id', $warehouse->id)
            ->where('inventory.quantity', '>', 0)
            ->whereNull('products.deleted_at')
            ->selectRaw('
                product_categories.name  AS category,
                COUNT(products.id)       AS product_count,
                SUM(inventory.quantity)  AS total_qty,
                SUM(inventory.quantity * products.cost_price) AS total_value
            ')
            ->groupBy('product_categories.id', 'product_categories.name')
            ->get();

        $grandTotal = $rows->sum('total_value');

        if ($request->expectsJson()) {
            return response()->json(['rows' => $rows, 'grand_total' => $grandTotal]);
        }

        return view('warehouses.reports.valuation', compact('warehouse', 'rows', 'grandTotal'));
    }
}
