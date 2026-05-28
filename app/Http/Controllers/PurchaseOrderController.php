<?php

namespace App\Http\Controllers;

use App\Models\{GoodsReceipt, GoodsReceiptItem, InventoryTransaction, InventoryTransactionItem, Product, PurchaseOrder, PurchaseOrderItem, StockBalance, Supplier, Warehouse};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['supplier', 'warehouse']);
        if ($request->status)      $query->where('status', $request->status);
        if ($request->supplier_id) $query->where('supplier_id', $request->supplier_id);
        $purchaseOrders = $query->latest()->paginate(15)->withQueryString();
        $suppliers = Supplier::active()->get();
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
            'supplier_id'     => 'required|exists:suppliers,id',
            'warehouse_id'    => 'required|exists:warehouses,id',
            'order_date'      => 'required|date',
            'expected_date'   => 'nullable|date|after_or_equal:order_date',
            'items'           => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_cost'        => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $subtotal = collect($request->items)->sum(fn($i) => $i['quantity_ordered'] * $i['unit_cost']);
            $tax      = $request->tax_amount ?? 0;
            $discount = $request->discount_amount ?? 0;

            $po = PurchaseOrder::create([
                'supplier_id'     => $request->supplier_id,
                'warehouse_id'    => $request->warehouse_id,
                'user_id'         => auth()->id(),
                'status'          => PurchaseOrder::STATUS_ORDERED,
                'order_date'      => $request->order_date,
                'expected_date'   => $request->expected_date,
                'notes'           => $request->notes,
                'subtotal'        => $subtotal,
                'tax_amount'      => $tax,
                'discount_amount' => $discount,
                'total_amount'    => $subtotal + $tax - $discount,
            ]);

            foreach ($request->items as $item) {
                $po->items()->create([
                    'product_id'       => $item['product_id'],
                    'quantity_ordered'  => $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_cost'        => $item['unit_cost'],
                    'subtotal'         => $item['quantity_ordered'] * $item['unit_cost'],
                ]);
            }
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase order created.');
    }

    public function show(PurchaseOrder $purchase)
    {
        $purchase->load(['supplier', 'warehouse', 'user', 'items.product.unit', 'goodsReceipts.items.product']);
        return view('purchases.show', compact('purchase'));
    }

    public function receive(PurchaseOrder $purchase)
    {
        $purchase->load(['items.product.unit', 'warehouse']);
        return view('purchases.receive', compact('purchase'));
    }

    public function storeReceipt(Request $request, PurchaseOrder $purchase)
    {
        $request->validate([
            'received_date'              => 'required|date',
            'items'                      => 'required|array',
            'items.*.purchase_order_item_id' => 'required|exists:purchase_order_items,id',
            'items.*.quantity_received'  => 'required|numeric|min:0',
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

                StockBalance::adjust($poItem->product_id, $purchase->warehouse_id, $item['quantity_received']);
            }

            // Update PO status
            $allReceived = $purchase->items->every(fn($i) => $i->fresh()->quantity_received >= $i->quantity_ordered);
            $purchase->update(['status' => $allReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_PARTIAL]);
        });

        return redirect()->route('purchases.show', $purchase)->with('success', 'Goods received successfully.');
    }

    public function destroy(PurchaseOrder $purchase)
    {
        if ($purchase->status !== PurchaseOrder::STATUS_DRAFT) {
            return back()->with('error', 'Only draft orders can be deleted.');
        }
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', 'Purchase order deleted.');
    }
}
