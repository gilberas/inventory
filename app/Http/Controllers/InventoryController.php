<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\InventoryTransactionItem;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    // ── Stock Levels (replaces stockOnHand) ──────────────────
    // Route: GET /inventory/stock  → name: inventory.index

    public function index(Request $request)
    {
        $warehouses      = Warehouse::active()->get();
        $selectedWarehouse = $request->warehouse_id;

        $balances = StockBalance::with(['product.unit', 'product.category', 'warehouse'])
            ->when($selectedWarehouse, fn($q) => $q->where('warehouse_id', $selectedWarehouse))
            ->when($request->search, fn($q) => $q->whereHas('product', fn($p) =>
                $p->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku',  'like', "%{$request->search}%")
            ))
            ->when($request->status === 'low', fn($q) => $q->whereHas('product', fn($p) =>
                $p->whereRaw('stock_balances.quantity_available <= products.minimum_stock')
            ))
            ->when($request->status === 'out', fn($q) => $q->where('quantity_available', '<=', 0))
            ->join('products', 'stock_balances.product_id', '=', 'products.id')
            ->select('stock_balances.*')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.index', compact('balances', 'warehouses', 'selectedWarehouse'));
    }

    // ── Alias kept so any old route /inventory/stock still works ──
    public function stockOnHand(Request $request)
    {
        return $this->index($request);
    }

    // ── Transaction Log ───────────────────────────────────────
    // Route: GET /inventory/transactions → name: inventory.transactions

    public function transactions(Request $request)
    {
        $warehouses = Warehouse::active()->get();

        $transactions = InventoryTransaction::with(['warehouse', 'createdBy'])
            ->withCount('items')
            ->when($request->type,         fn($q) => $q->where('transaction_type', $request->type))
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->date_from,    fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to,      fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $types = InventoryTransaction::TYPES;

        return view('inventory.transactions', compact('transactions', 'warehouses', 'types'));
    }

    // ── Show single transaction ───────────────────────────────
    // Route: GET /inventory/transactions/{id} → name: inventory.transactions.show

    public function showTransaction(int $id)
    {
        $transaction = InventoryTransaction::with([
            'items.product.unit',
            'items.batch',
            'items.location',
            'warehouse',
            'createdBy',
        ])->findOrFail($id);

        return view('inventory.transaction-show', compact('transaction'));
    }

    // ── Stock Adjustment form ─────────────────────────────────
    // Route: GET /inventory/adjustment → name: inventory.adjustment

    public function adjustment(Request $request)
    {
        $products   = Product::active()->with('unit')->orderBy('name')->get();
        $warehouses = Warehouse::active()->get();

        return view('inventory.adjustment', compact('products', 'warehouses'));
    }

    // ── Store Stock Adjustment ────────────────────────────────
    // Route: POST /inventory/adjustment → name: inventory.adjustment.store

    public function storeAdjustment(Request $request)
    {
        $request->validate([
            'warehouse_id'           => 'required|exists:warehouses,id',
            'notes'                  => 'nullable|string|max:500',
            'items'                  => 'required|array|min:1',
            'items.*.product_id'     => 'required|exists:products,id',
            'items.*.quantity'       => 'required|numeric|not_in:0',
            'items.*.reason'         => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($request) {
            $transaction = InventoryTransaction::create([
                'transaction_type' => 'ADJUSTMENT',
                'warehouse_id'     => $request->warehouse_id,
                'notes'            => $request->notes,
                'created_by'       => auth()->id(),
                'transaction_date' => now(),
            ]);

            foreach ($request->items as $item) {
                $qty = (float) $item['quantity'];

                $transaction->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $qty,
                    'unit_cost'  => 0,
                    'notes'      => $item['reason'],
                ]);

                // Update stock balance
                if ($qty > 0) {
                    StockBalance::adjustStock($item['product_id'], $request->warehouse_id, $qty);
                }
            }
        });

        return redirect()
            ->route('inventory.index')
            ->with('success', 'Stock adjustment saved successfully.');
    }

    // ── Stock Valuation ───────────────────────────────────────
    // Route: GET /inventory/valuation → name: inventory.valuation

    public function valuation(Request $request)
    {
        $warehouses = Warehouse::active()->get();

        $valuation = StockBalance::with(['product', 'warehouse'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->where('quantity_available', '>', 0)
            ->get()
            ->map(function ($balance) {
                return [
                    'product'    => $balance->product->name,
                    'sku'        => $balance->product->sku,
                    'warehouse'  => $balance->warehouse->name,
                    'quantity'   => $balance->quantity_available,
                    'cost_price' => $balance->product->cost_price,
                    'total_value'=> $balance->quantity_available * $balance->product->cost_price,
                ];
            });

        $totalValue = $valuation->sum('total_value');

        return view('inventory.valuation', compact('valuation', 'totalValue', 'warehouses'));
    }

    // ── Low Stock Report ──────────────────────────────────────
    // Route: GET /inventory/low-stock → name: inventory.low-stock

    public function lowStock(Request $request)
    {
        $warehouses = Warehouse::active()->get();

        $items = StockBalance::with(['product.unit', 'product.category', 'warehouse'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->join('products', 'stock_balances.product_id', '=', 'products.id')
            ->whereRaw('stock_balances.quantity_available <= products.minimum_stock')
            ->select('stock_balances.*')
            ->orderByRaw('stock_balances.quantity_available ASC')
            ->paginate(20)
            ->withQueryString();

        return view('inventory.low-stock', compact('items', 'warehouses'));
    }

    // ── AJAX: get current stock for a product in a warehouse ──
    // Route: GET /inventory/stock-level → name: inventory.stock-level

    public function getStockLevel(Request $request)
    {
        $request->validate([
            'product_id'   => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        $balance = StockBalance::where('product_id',   $request->product_id)
                               ->where('warehouse_id', $request->warehouse_id)
                               ->first();

        return response()->json([
            'quantity_available' => $balance?->quantity_available ?? 0,
            'quantity_reserved'  => $balance?->quantity_reserved  ?? 0,
            'quantity_on_hand'   => $balance?->quantity_on_hand   ?? 0,
        ]);
    }
}