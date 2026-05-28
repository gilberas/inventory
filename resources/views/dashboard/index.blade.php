@extends('layouts.app')
@section('title', 'Dashboard')
@section('header', 'Dashboard')

@section('content')
<!-- Stat Cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    @php
    $cards = [
        ['label'=>'Products',    'value'=>$stats['total_products'],   'icon'=>'fas fa-boxes',              'color'=>'blue'],
        ['label'=>'Warehouses',  'value'=>$stats['total_warehouses'],  'icon'=>'fas fa-warehouse',          'color'=>'indigo'],
        ['label'=>'Low Stock',   'value'=>$stats['low_stock_count'],   'icon'=>'fas fa-exclamation-triangle','color'=>'red'],
        ['label'=>'Pending POs', 'value'=>$stats['pending_pos'],       'icon'=>'fas fa-shopping-cart',      'color'=>'yellow'],
    ];
    @endphp
    @foreach($cards as $card)
    <div class="bg-white rounded-xl shadow-sm p-5 flex items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-{{ $card['color'] }}-100 flex items-center justify-center">
            <i class="{{ $card['icon'] }} text-{{ $card['color'] }}-600 text-xl"></i>
        </div>
        <div>
            <p class="text-2xl font-bold text-gray-900">{{ $card['value'] }}</p>
            <p class="text-sm text-gray-500">{{ $card['label'] }}</p>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Low Stock Alert -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Low Stock Alerts</h3>
            <a href="{{ route('reports.low-stock') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($lowStockProducts as $product)
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $product->name }}</p>
                    <p class="text-xs text-gray-500">SKU: {{ $product->sku }}</p>
                </div>
                <div class="text-right">
                    <span class="text-sm font-bold text-red-600">{{ $product->totalStock() }} {{ $product->unit->abbreviation }}</span>
                    <p class="text-xs text-gray-400">Min: {{ $product->minimum_stock }}</p>
                </div>
            </div>
            @empty
            <div class="px-5 py-8 text-center text-gray-400">
                <i class="fas fa-check-circle text-3xl text-green-400 mb-2"></i>
                <p class="text-sm">All stock levels are healthy.</p>
            </div>
            @endforelse
        </div>
    </div>

    <!-- Recent Sales Orders -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Recent Sales Orders</h3>
            <a href="{{ route('sales.index') }}" class="text-sm text-blue-600 hover:underline">View all</a>
        </div>
        <div class="divide-y divide-gray-50">
            @forelse($recentSalesOrders as $order)
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $order->so_number }}</p>
                    <p class="text-xs text-gray-500">{{ $order->customer->name }}</p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold text-gray-800">{{ number_format($order->total_amount, 2) }}</p>
                    <span class="px-2 py-0.5 text-xs rounded-full
                        {{ $order->status === 'delivered' ? 'bg-green-100 text-green-700' :
                          ($order->status === 'confirmed' ? 'bg-blue-100 text-blue-700' :
                          ($order->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700')) }}">
                        {{ ucfirst($order->status) }}
                    </span>
                </div>
            </div>
            @empty
            <div class="px-5 py-8 text-center text-gray-400 text-sm">No recent sales orders.</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Recent Transactions -->
<div class="mt-6 bg-white rounded-xl shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Recent Inventory Transactions</h3>
    </div>
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-5 py-3 text-left">Reference</th>
                <th class="px-5 py-3 text-left">Type</th>
                <th class="px-5 py-3 text-left">Warehouse</th>
                <th class="px-5 py-3 text-left">By</th>
                <th class="px-5 py-3 text-left">Date</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($recentTransactions as $txn)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs text-gray-600">{{ $txn->reference_number }}</td>
                <td class="px-5 py-3">
                    <span class="px-2 py-0.5 rounded-full text-xs font-medium
                        {{ $txn->type === 'in' ? 'bg-green-100 text-green-700' :
                          ($txn->type === 'out' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700') }}">
                        {{ ucfirst($txn->type) }}
                    </span>
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $txn->warehouse->name ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-600">{{ $txn->user->name ?? '—' }}</td>
                <td class="px-5 py-3 text-gray-400">{{ $txn->transaction_date->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-5 py-8 text-center text-gray-400">No transactions yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
