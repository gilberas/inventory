<?php

namespace App\Http\Controllers;

use App\Models\StockBalance;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\InventoryTransaction;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // ── REPORTS OVERVIEW ─────────────────────────────────────────────────────
    public function index()
    {
        // Summary numbers shown on the reports landing page
        $summary = [
            'total_stock_value'  => StockBalance::join('products', 'products.id', '=', 'stock_balances.product_id')
                                        ->selectRaw('SUM(stock_balances.quantity_available * products.cost_price) as total')
                                        ->value('total') ?? 0,

            'low_stock_count'    => Product::active()->lowStock()->count(),

            'expiring_soon'      => ProductBatch::expiringSoon(30)->count(),

            'pending_po_value'   => PurchaseOrder::whereIn('status', ['DRAFT','PENDING_APPROVAL','APPROVED'])
                                        ->sum('total_amount'),

            'pending_sales_value'=> SalesOrder::whereIn('status', ['CONFIRMED','PROCESSING'])
                                        ->sum('total_amount'),

            'transactions_today' => InventoryTransaction::whereDate('transaction_date', today())->count(),
        ];

        $warehouses = Warehouse::active()->get();

        return view('reports.index', compact('summary', 'warehouses'));
    }

    // ── STOCK ON HAND ────────────────────────────────────────────────────────
    public function stockOnHand(Request $request)
    {
        $balances = StockBalance::with(['product.unit', 'product.category', 'warehouse'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->search, fn($q) => $q->whereHas('product', fn($pq) =>
                $pq->where('name', 'like', "%{$request->search}%")
                   ->orWhere('sku',  'like', "%{$request->search}%")
            ))
            ->paginate(30)
            ->withQueryString();

        $warehouses  = Warehouse::active()->get();
        $totalValue  = StockBalance::join('products', 'products.id', '=', 'stock_balances.product_id')
                            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
                            ->selectRaw('SUM(stock_balances.quantity_available * products.cost_price) as total')
                            ->value('total') ?? 0;

        return view('reports.stock-on-hand', compact('balances', 'warehouses', 'totalValue'));
    }

    // ── LOW STOCK ────────────────────────────────────────────────────────────
    public function lowStock()
    {
        $products = Product::active()
            ->lowStock()
            ->with(['unit', 'category', 'stockBalances.warehouse'])
            ->orderByRaw('(SELECT COALESCE(SUM(quantity_available),0) FROM stock_balances WHERE product_id = products.id) ASC')
            ->get();

        return view('reports.low-stock', compact('products'));
    }

    // ── EXPIRY REPORT ────────────────────────────────────────────────────────
    public function expiry(Request $request)
    {
        $days    = (int) ($request->days ?? 30);

        $expiringSoon = ProductBatch::with('product.unit')
            ->expiringSoon($days)
            ->orderBy('expiry_date')
            ->get();

        $expired = ProductBatch::with('product.unit')
            ->expired()
            ->orderBy('expiry_date', 'desc')
            ->get();

        return view('reports.expiry', compact('expiringSoon', 'expired', 'days'));
    }

    // ── INVENTORY VALUATION ──────────────────────────────────────────────────
    public function valuation(Request $request)
    {
        $query = StockBalance::with(['product.category', 'warehouse'])
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id));

        $data = $query->get()->map(fn($b) => [
            'product'          => $b->product->name,
            'sku'              => $b->product->sku,
            'category'         => $b->product->category?->name ?? '—',
            'warehouse'        => $b->warehouse->name,
            'qty'              => $b->quantity_available,
            'cost_price'       => $b->product->cost_price,
            'selling_price'    => $b->product->selling_price,
            'total_cost_value' => $b->quantity_available * $b->product->cost_price,
            'total_sell_value' => $b->quantity_available * $b->product->selling_price,
        ]);

        $totalCost  = $data->sum('total_cost_value');
        $totalSell  = $data->sum('total_sell_value');
        $warehouses = Warehouse::active()->get();

        return view('reports.valuation', compact('data', 'warehouses', 'totalCost', 'totalSell'));
    }

    // ── PURCHASE REPORT ──────────────────────────────────────────────────────
    public function purchases(Request $request)
    {
        $orders = PurchaseOrder::with(['supplier', 'warehouse', 'createdBy'])
            ->when($request->from,        fn($q) => $q->whereDate('order_date', '>=', $request->from))
            ->when($request->to,          fn($q) => $q->whereDate('order_date', '<=', $request->to))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->latest('order_date')
            ->get();

        $totalAmount = $orders->sum('total_amount');
        $statuses    = PurchaseOrder::STATUSES;

        return view('reports.purchases', compact('orders', 'totalAmount', 'statuses'));
    }

    // ── SALES REPORT ─────────────────────────────────────────────────────────
    public function sales(Request $request)
    {
        $orders = SalesOrder::with(['customer', 'warehouse', 'createdBy'])
            ->when($request->from,        fn($q) => $q->whereDate('order_date', '>=', $request->from))
            ->when($request->to,          fn($q) => $q->whereDate('order_date', '<=', $request->to))
            ->when($request->status,      fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->latest('order_date')
            ->get();

        $totalAmount = $orders->sum('total_amount');
        $statuses    = SalesOrder::STATUSES;

        return view('reports.sales', compact('orders', 'totalAmount', 'statuses'));
    }

    // ── STOCK MOVEMENT HISTORY ───────────────────────────────────────────────
    public function movements(Request $request)
    {
        $transactions = InventoryTransaction::with(['items.product.unit', 'warehouse', 'createdBy'])
            ->when($request->product_id,   fn($q) => $q->whereHas('items', fn($iq) =>
                $iq->where('product_id', $request->product_id)
            ))
            ->when($request->warehouse_id, fn($q) => $q->where('warehouse_id', $request->warehouse_id))
            ->when($request->type,         fn($q) => $q->where('transaction_type', $request->type))
            ->when($request->from,         fn($q) => $q->whereDate('transaction_date', '>=', $request->from))
            ->when($request->to,           fn($q) => $q->whereDate('transaction_date', '<=', $request->to))
            ->latest('transaction_date')
            ->paginate(30)
            ->withQueryString();

        $products   = Product::active()->orderBy('name')->get();
        $warehouses = Warehouse::active()->get();
        $types      = InventoryTransaction::TYPES;

        return view('reports.movements', compact('transactions', 'products', 'warehouses', 'types'));
    }
}