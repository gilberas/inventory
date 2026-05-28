<?php

namespace App\Http\Controllers;

use App\Models\{Customer, InventoryTransaction, Product, PurchaseOrder, SalesOrder, Supplier, Warehouse};
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_products'   => Product::active()->count(),
            'total_warehouses' => Warehouse::active()->count(),
            'total_suppliers'  => Supplier::active()->count(),
            'total_customers'  => Customer::active()->count(),
            'low_stock_count'  => Product::active()->get()->filter->isLowStock()->count(),
            'pending_pos' => PurchaseOrder::where('status', 'APPROVED')->count(),
'pending_sos' => SalesOrder::where('status', 'CONFIRMED')->count(),
        ];

        $recentTransactions = InventoryTransaction::with(['user', 'warehouse'])
            ->latest()->limit(10)->get();

        $recentSalesOrders = SalesOrder::with(['customer'])
            ->latest()->limit(5)->get();

        $lowStockProducts = Product::active()->with(['unit', 'stockBalances'])
            ->get()->filter->isLowStock()->take(10)->values();

        $monthlyStats = SalesOrder::selectRaw('MONTH(created_at) as month, SUM(total_amount) as total')
            ->whereYear('created_at', date('Y'))
            ->groupBy('month')->orderBy('month')->get();

        return view('dashboard.index', compact(
            'stats', 'recentTransactions', 'recentSalesOrders', 'lowStockProducts', 'monthlyStats'
        ));
    }
}
