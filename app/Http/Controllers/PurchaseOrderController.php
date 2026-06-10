<?php

namespace App\Http\Controllers;

use App\Mail\PurchaseOrderMail;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PurchaseOrderController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'warehouse']);
        if ($request->status)      $query->where('status', $request->status);
        if ($request->supplier_id) $query->where('supplier_id', $request->supplier_id);
        $purchaseOrders = $query->latest()->paginate(15)->withQueryString();
        $suppliers      = Supplier::active()->get();
        return view('purchases.index', compact('purchaseOrders', 'suppliers'));
    }

    public function create()
    {
        $suppliers  = Supplier::active()->get();
        $warehouses = Warehouse::active()->get();
        $products   = Product::active()->with('unit')->get();
        return view('purchases.create', compact('suppliers', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'              => 'required|exists:suppliers,id',
            'warehouse_id'             => 'required|exists:warehouses,id',
            'order_date'               => 'required|date',
            'expected_date'            => 'nullable|date|after_or_equal:order_date',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_cost'        => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $total = collect($request->items)->sum(fn($i) => $i['quantity_ordered'] * $i['unit_cost']);

            $po = PurchaseOrder::create([
                'supplier_id'   => $request->supplier_id,
                'warehouse_id'  => $request->warehouse_id,
                'branch_id'     => $request->branch_id,
                'requisition_id' => $request->requisition_id,
                'created_by'    => auth()->id(),
                'status'        => PurchaseOrder::STATUS_DRAFT,
                'order_date'    => $request->order_date,
                'expected_date' => $request->expected_date,
                'notes'         => $request->notes,
                'total_amount'  => $total,
            ]);

            foreach ($request->items as $item) {
                $po->items()->create([
                    'product_id'       => $item['product_id'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost'        => $item['unit_cost'],
                ]);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => PurchaseOrder::class,
                'model_id'   => $po->id,
                'new_values' => ['status' => PurchaseOrder::STATUS_DRAFT],
            ]);
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'warehouse', 'createdBy', 'items.product.unit', 'goodsReceipts.items.product', 'goodsReceivedNotes.items.product']);
        return view('purchases.show', compact('purchase'));
    }

    public function edit(PurchaseOrder $purchase)
    {
        abort_if($purchase->status !== PurchaseOrder::STATUS_DRAFT, 403, 'Only draft orders can be edited.');
        $suppliers  = Supplier::active()->get();
        $warehouses = Warehouse::active()->get();
        $products   = Product::active()->with('unit')->get();
        return view('purchases.edit', compact('purchase', 'suppliers', 'warehouses', 'products'));
    }

    public function update(Request $request, PurchaseOrder $purchase)
    {
        abort_if($purchase->status !== PurchaseOrder::STATUS_DRAFT, 403, 'Only draft orders can be updated.');

        $request->validate([
            'notes'         => 'nullable|string',
            'expected_date' => 'nullable|date',
        ]);

        $purchase->update($request->only(['notes', 'expected_date']));

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'updated',
            'model_type' => PurchaseOrder::class,
            'model_id'   => $purchase->id,
            'new_values' => $request->only(['notes', 'expected_date']),
        ]);

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase order updated.');
    }

    public function submit(PurchaseOrder $purchase)
    {
        abort_if($purchase->status !== PurchaseOrder::STATUS_DRAFT, 422, 'Only draft orders can be submitted.');

        $purchase->update(['status' => PurchaseOrder::STATUS_PENDING_APPROVAL]);

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'submitted',
            'model_type' => PurchaseOrder::class,
            'model_id'   => $purchase->id,
            'new_values' => ['status' => PurchaseOrder::STATUS_PENDING_APPROVAL],
        ]);

        return redirect()->route('purchases.show', $purchase)->with('success', 'Order submitted for approval.');
    }

    public function approve(PurchaseOrder $purchase)
    {
        abort_if(
            !in_array($purchase->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_PENDING_APPROVAL]),
            422,
            'Order cannot be approved in its current state.'
        );

        DB::transaction(function () use ($purchase) {
            $purchase->update([
                'status'      => PurchaseOrder::STATUS_APPROVED,
                'approved_by' => auth()->id(),
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'approved',
                'model_type' => PurchaseOrder::class,
                'model_id'   => $purchase->id,
                'new_values' => ['status' => PurchaseOrder::STATUS_APPROVED],
            ]);

            // Email supplier (Hard Rule §3: queued via ShouldQueue on PurchaseOrderMail)
            if ($purchase->supplier?->email) {
                Mail::to($purchase->supplier->email)->queue(new PurchaseOrderMail($purchase));
            }
        });

        return redirect()->route('purchases.show', $purchase)->with('success', 'Purchase order approved and sent to supplier.');
    }

    public function pdf(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'warehouse', 'items.product.unit']);

        $pdf = Pdf::loadView('purchases.pdf', ['po' => $purchase]);

        return $pdf->download("PO-{$purchase->reference_no}.pdf");
    }

    public function receive(PurchaseOrder $purchase)
    {
        $purchase->load(['items.product.unit', 'warehouse']);
        return view('purchases.receive', compact('purchase'));
    }

    // Legacy receipt creation (old GRN flow — kept for backward compat).
    // New workflow uses GoodsReceivedNoteController.
    public function storeReceipt(Request $request, PurchaseOrder $purchase)
    {
        $request->validate([
            'received_date'                  => 'required|date',
            'items'                          => 'required|array',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received'      => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $purchase) {
            $receipt = GoodsReceipt::create([
                'purchase_order_id' => $purchase->id,
                'warehouse_id'      => $purchase->warehouse_id,
                'received_by'       => auth()->id(),
                'received_date'     => $request->received_date,
                'notes'             => $request->notes,
            ]);

            $txn = InventoryTransaction::create([
                'type'             => InventoryTransaction::TYPE_IN,
                'reference_type'   => InventoryTransaction::REF_PURCHASE,
                'reference_id'     => $purchase->id,
                'warehouse_id'     => $purchase->warehouse_id,
                'user_id'          => auth()->id(),
                'transaction_date' => $request->received_date,
            ]);

            foreach ($request->items as $item) {
                if ($item['quantity_received'] <= 0) continue;

                $poItem = PurchaseOrderItem::find($item['purchase_order_item_id']);

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'product_id'             => $poItem->product_id,
                    'quantity_received'      => $item['quantity_received'],
                    'unit_cost'              => $poItem->unit_cost,
                ]);

                $poItem->increment('quantity_received', $item['quantity_received']);

                InventoryTransactionItem::create([
                    'inventory_transaction_id' => $txn->id,
                    'product_id'               => $poItem->product_id,
                    'quantity'                 => $item['quantity_received'],
                    'unit_cost'                => $poItem->unit_cost,
                ]);

                // Fixed: was StockBalance::adjust() (non-existent). Use InventoryService::stockIn().
                $this->inventoryService->stockIn(
                    productId:   $poItem->product_id,
                    warehouseId: $purchase->warehouse_id,
                    qty:         (float) $item['quantity_received'],
                    unitCost:    (float) $poItem->unit_cost,
                    refType:     'purchase',
                    refId:       $receipt->id,
                    userId:      auth()->id(),
                );
            }

            $allReceived = $purchase->items->every(
                fn($i) => (float) $i->fresh()->quantity_received >= (float) $i->quantity_ordered
            );
            $purchase->update([
                'status' => $allReceived
                    ? PurchaseOrder::STATUS_RECEIVED
                    : PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
            ]);
        });

        return redirect()->route('purchases.show', $purchase)->with('success', 'Goods received successfully.');
    }

    public function destroy(PurchaseOrder $purchase)
    {
        abort_if($purchase->status !== PurchaseOrder::STATUS_DRAFT, 403, 'Only draft orders can be deleted.');

        $purchase->delete();

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'deleted',
            'model_type' => PurchaseOrder::class,
            'model_id'   => $purchase->id,
            'old_values' => ['status' => $purchase->status],
        ]);

        return redirect()->route('purchases.index')->with('success', 'Purchase order deleted.');
    }
}
