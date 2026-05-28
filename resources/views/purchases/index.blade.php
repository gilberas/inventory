@extends('layouts.app')
@section('title', 'Purchase Orders')
@section('header', 'Purchase Orders')

@section('header-actions')
    <a href="{{ route('purchases.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New PO</a>
@endsection

@section('content')
<div class="bg-white rounded-xl shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">All</option>
                @foreach(['draft','ordered','partial','received','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Supplier</label>
            <select name="supplier_id" class="input">
                <option value="">All</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(request('supplier_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-secondary">Filter</button>
        <a href="{{ route('purchases.index') }}" class="btn-ghost">Reset</a>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-5 py-3 text-left">PO Number</th>
                <th class="px-5 py-3 text-left">Supplier</th>
                <th class="px-5 py-3 text-left">Warehouse</th>
                <th class="px-5 py-3 text-left">Order Date</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3 text-center">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($purchaseOrders as $po)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs font-medium text-gray-700">{{ $po->po_number }}</td>
                <td class="px-5 py-3">{{ $po->supplier->name }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $po->warehouse->name }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $po->order_date->format('d M Y') }}</td>
                <td class="px-5 py-3 text-right font-medium">{{ number_format($po->total_amount, 2) }}</td>
                <td class="px-5 py-3 text-center">
                    @php $colors = ['draft'=>'gray','ordered'=>'blue','partial'=>'yellow','received'=>'green','cancelled'=>'red']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs bg-{{ $colors[$po->status] ?? 'gray' }}-100 text-{{ $colors[$po->status] ?? 'gray' }}-700">
                        {{ ucfirst($po->status) }}
                    </span>
                </td>
                <td class="px-5 py-3 text-right space-x-2">
                    <a href="{{ route('purchases.show', $po) }}" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                    @if(in_array($po->status, ['ordered','partial']))
                    <a href="{{ route('purchases.receive', $po) }}" class="text-green-500 hover:text-green-700" title="Receive goods">
                        <i class="fas fa-truck-loading"></i>
                    </a>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No purchase orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-5 py-4 border-t border-gray-100">{{ $purchaseOrders->links() }}</div>
</div>
@endsection
