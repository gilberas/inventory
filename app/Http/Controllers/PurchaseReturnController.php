<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseReturn;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseReturnController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $returns = PurchaseReturn::with(['supplier', 'grn', 'createdBy'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json(['data' => $returns]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'        => 'required|exists:suppliers,id',
            'grn_id'             => 'required|exists:goods_received_notes,id',
            'reason'             => 'required|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'        => 'required|numeric|min:0.01',
            'items.*.unit_cost'  => 'required|numeric|min:0',
        ]);

        $grn = GoodsReceivedNote::findOrFail($request->grn_id);
        abort_if($grn->status !== GoodsReceivedNote::STATUS_CONFIRMED, 422, 'Can only return items from a confirmed GRN.');

        $purchaseReturn = null;
        DB::transaction(function () use ($request, $grn, &$purchaseReturn) {
            $totalAmount = collect($request->items)->sum(fn($i) => $i['qty'] * $i['unit_cost']);

            $purchaseReturn = PurchaseReturn::create([
                'supplier_id'  => $request->supplier_id,
                'grn_id'       => $request->grn_id,
                'warehouse_id' => $grn->warehouse_id,
                'created_by'   => auth()->id(),
                'total_amount' => $totalAmount,
                'reason'       => $request->reason,
                'status'       => 'completed',
            ]);

            foreach ($request->items as $item) {
                $purchaseReturn->items()->create([
                    'product_id' => $item['product_id'],
                    'qty'        => $item['qty'],
                    'unit_cost'  => $item['unit_cost'],
                ]);

                // Reverse the GRN stock-in (Hard Rule §3: all mutations in DB::transaction)
                $this->inventoryService->stockOut(
                    productId:   $item['product_id'],
                    warehouseId: $grn->warehouse_id,
                    qty:         (float) $item['qty'],
                    refType:     'purchase_return',
                    refId:       $purchaseReturn->id,
                    userId:      auth()->id(),
                    notes:       "Return ref: {$purchaseReturn->reference_no}",
                );
            }

            // Decrement supplier balance (goods returned reduce what we owe)
            $purchaseReturn->supplier()->decrement('balance', $totalAmount);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'purchase_return',
                'model_type' => PurchaseReturn::class,
                'model_id'   => $purchaseReturn->id,
                'new_values' => ['total_amount' => $totalAmount, 'grn_id' => $request->grn_id],
            ]);
        });

        return redirect()->back()->with('success', "Return {$purchaseReturn->reference_no} completed.");
    }

    public function show(PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['items.product', 'supplier', 'grn', 'createdBy']);
        return response()->json($purchaseReturn);
    }
}
