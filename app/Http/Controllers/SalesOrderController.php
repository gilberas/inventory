<?php

namespace App\Http\Controllers;

use App\Models\{Customer, InventoryTransaction, InventoryTransactionItem, Product, SalesOrder, SalesOrderItem, SalesPayment, StockBalance, Warehouse};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        $query = SalesOrder::with(['customer', 'warehouse']);
        if ($request->status)      $query->where('status', $request->status);
        if ($request->customer_id) $query->where('customer_id', $request->customer_id);
        $salesOrders = $query->latest()->paginate(15)->withQueryString();
        $customers   = Customer::active()->get();
        return view('sales.index', compact('salesOrders', 'customers'));
    }

    public function create()
    {
        $customers  = Customer::active()->get();
        $warehouses = Warehouse::active()->get();
        $products   = Product::active()->with(['unit', 'stockBalances'])->get();
        return view('sales.create', compact('customers', 'warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id'              => 'required|exists:customers,id',
            'warehouse_id'             => 'required|exists:warehouses,id',
            'order_date'               => 'required|date',
            'items'                    => 'required|array|min:1',
            'items.*.product_id'       => 'required|exists:products,id',
            'items.*.quantity_ordered' => 'required|numeric|min:0.01',
            'items.*.unit_price'       => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $subtotal = collect($request->items)->sum(fn($i) => $i['quantity_ordered'] * $i['unit_price']);
            $tax      = $request->tax_amount ?? 0;
            $discount = $request->discount_amount ?? 0;

            $so = SalesOrder::create([
                'customer_id'     => $request->customer_id,
                'warehouse_id'    => $request->warehouse_id,
                'user_id'         => auth()->id(),
                'status'          => SalesOrder::STATUS_CONFIRMED,
                'order_date'      => $request->order_date,
                'delivery_date'   => $request->delivery_date,
                'notes'           => $request->notes,
                'subtotal'        => $subtotal,
                'tax_amount'      => $tax,
                'discount_amount' => $discount,
                'total_amount'    => $subtotal + $tax - $discount,
            ]);

            foreach ($request->items as $item) {
                $so->items()->create([
                    'product_id'         => $item['product_id'],
                    'quantity_ordered'   => $item['quantity_ordered'],
                    'quantity_delivered' => 0,
                    'unit_price'         => $item['unit_price'],
                    'discount'           => $item['discount'] ?? 0,
                    'subtotal'           => $item['quantity_ordered'] * $item['unit_price'],
                ]);
            }
        });

        return redirect()->route('sales.index')->with('success', 'Sales order created.');
    }

    public function show(SalesOrder $sale)
    {
        $sale->load(['customer', 'warehouse', 'user', 'items.product.unit', 'payments']);
        return view('sales.show', compact('sale'));
    }

    public function deliver(SalesOrder $sale)
    {
        $request = request();
        $request->validate([
            'items'                        => 'required|array',
            'items.*.sales_order_item_id'  => 'required|exists:sales_order_items,id',
            'items.*.quantity_delivered'   => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $sale) {
            $txn = InventoryTransaction::create([
                'type'             => InventoryTransaction::TYPE_OUT,
                'reference_type'   => InventoryTransaction::REF_SALE,
                'reference_id'     => $sale->id,
                'warehouse_id'     => $sale->warehouse_id,
                'user_id'          => auth()->id(),
                'transaction_date' => now(),
            ]);

            foreach ($request->items as $item) {
                if ($item['quantity_delivered'] <= 0) continue;

                $soItem = SalesOrderItem::find($item['sales_order_item_id']);
                $soItem->increment('quantity_delivered', $item['quantity_delivered']);

                InventoryTransactionItem::create([
                    'inventory_transaction_id' => $txn->id,
                    'product_id'               => $soItem->product_id,
                    'quantity'                 => $item['quantity_delivered'],
                    'unit_cost'                => $soItem->unit_price,
                ]);

                StockBalance::adjust($soItem->product_id, $sale->warehouse_id, -$item['quantity_delivered']);
            }

            $allDelivered = $sale->items->every(fn($i) => $i->fresh()->quantity_delivered >= $i->quantity_ordered);
            $sale->update(['status' => $allDelivered ? SalesOrder::STATUS_DELIVERED : SalesOrder::STATUS_PARTIAL]);
        });

        return redirect()->route('sales.show', $sale)->with('success', 'Delivery recorded.');
    }

    public function addPayment(Request $request, SalesOrder $sale)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|string',
            'payment_date'   => 'required|date',
            'reference'      => 'nullable|string',
        ]);

        $sale->payments()->create($request->only('amount', 'payment_method', 'payment_date', 'reference', 'notes'));
        return redirect()->route('sales.show', $sale)->with('success', 'Payment recorded.');
    }

    public function destroy(SalesOrder $sale)
    {
        if ($sale->status !== SalesOrder::STATUS_DRAFT) {
            return back()->with('error', 'Only draft orders can be deleted.');
        }
        $sale->delete();
        return redirect()->route('sales.index')->with('success', 'Sales order deleted.');
    }
}
