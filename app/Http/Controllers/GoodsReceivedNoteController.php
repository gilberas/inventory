<?php

namespace App\Http\Controllers;

use App\Events\GRNConfirmed;
use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceivedNoteController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $grns = GoodsReceivedNote::with(['purchaseOrder.supplier', 'receivedBy'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $grns]);
        }

        return view('grn.index', compact('grns'));
    }

    public function create()
    {
        $pos        = PurchaseOrder::whereIn('status', [PurchaseOrder::STATUS_APPROVED, PurchaseOrder::STATUS_PARTIALLY_RECEIVED])
            ->with('supplier')
            ->get();
        $warehouses = Warehouse::active()->get();

        return response()->json(['purchase_orders' => $pos, 'warehouses' => $warehouses]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'purchase_order_id'          => 'required|exists:purchase_orders,id',
            'warehouse_id'               => 'required|exists:warehouses,id',
            'notes'                      => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.qty_received'       => 'required|numeric|min:0.01',
            'items.*.unit_cost'          => 'required|numeric|min:0',
            'items.*.po_item_id'         => 'nullable|exists:purchase_order_items,id',
            'items.*.expiry_date'        => 'nullable|date',
        ]);

        $grn = null;
        DB::transaction(function () use ($request, &$grn) {
            $grn = GoodsReceivedNote::create([
                'purchase_order_id' => $request->purchase_order_id,
                'received_by'       => auth()->id(),
                'warehouse_id'      => $request->warehouse_id,
                'status'            => GoodsReceivedNote::STATUS_DRAFT,
                'notes'             => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $grn->items()->create([
                    'product_id'  => $item['product_id'],
                    'po_item_id'  => $item['po_item_id'] ?? null,
                    'qty_received' => $item['qty_received'],
                    'unit_cost'   => $item['unit_cost'],
                    'expiry_date' => $item['expiry_date'] ?? null,
                ]);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => GoodsReceivedNote::class,
                'model_id'   => $grn->id,
                'new_values' => ['status' => GoodsReceivedNote::STATUS_DRAFT, 'po_id' => $request->purchase_order_id],
            ]);
        });

        return redirect()->back()->with('success', "GRN {$grn->reference_no} created.");
    }

    public function show(GoodsReceivedNote $grn)
    {
        $grn->load(['items.product', 'purchaseOrder.supplier', 'receivedBy', 'warehouse']);
        return response()->json($grn);
    }

    public function confirm(GoodsReceivedNote $grn)
    {
        abort_if($grn->status !== GoodsReceivedNote::STATUS_DRAFT, 422, 'Only draft GRNs can be confirmed.');

        DB::transaction(function () use ($grn) {
            $grn->load(['items', 'purchaseOrder.items']);

            // Stock in each item via InventoryService
            foreach ($grn->items as $grnItem) {
                $this->inventoryService->stockIn(
                    productId:   $grnItem->product_id,
                    warehouseId: $grn->warehouse_id,
                    qty:         (float) $grnItem->qty_received,
                    unitCost:    (float) $grnItem->unit_cost,
                    refType:     'purchase',
                    refId:       $grn->id,
                    userId:      auth()->id(),
                    notes:       "GRN {$grn->reference_no}",
                );

                // Update PO item quantity_received (match by product_id)
                $poItem = PurchaseOrderItem::where('purchase_order_id', $grn->purchase_order_id)
                    ->where('product_id', $grnItem->product_id)
                    ->first();

                if ($poItem) {
                    $poItem->increment('quantity_received', $grnItem->qty_received);
                }
            }

            // Determine PO status after this GRN
            $po = $grn->purchaseOrder;
            $po->load('items');
            $allFulfilled = $po->items->every(
                fn($item) => (float) $item->fresh()->quantity_received >= (float) $item->quantity_ordered
            );

            $po->update([
                'status' => $allFulfilled
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ]);

            // Increment supplier balance by GRN total cost
            $grnTotal = $grn->items->sum(fn($i) => (float) $i->qty_received * (float) $i->unit_cost);
            $po->supplier()->increment('balance', $grnTotal);

            // Confirm GRN
            $grn->update([
                'status'      => GoodsReceivedNote::STATUS_CONFIRMED,
                'received_at' => now(),
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'confirmed',
                'model_type' => GoodsReceivedNote::class,
                'model_id'   => $grn->id,
                'new_values' => ['status' => GoodsReceivedNote::STATUS_CONFIRMED, 'po_status' => $po->fresh()->status],
            ]);

            GRNConfirmed::dispatch($grn);
        });

        return redirect()->back()->with('success', "GRN {$grn->reference_no} confirmed. Inventory updated.");
    }
}
