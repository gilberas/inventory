@extends('layouts.app')
@section('title', 'Sales Orders')
@section('header', 'Sales Orders')

@section('header-actions')
    <a href="{{ route('sales.create') }}" class="btn-primary"><i class="fas fa-plus mr-1"></i> New Order</a>
@endsection

@section('content')
<div class="bg-white rounded-xl shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">All</option>
                @foreach(['draft','confirmed','partial','delivered','cancelled'] as $s)
                    <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Customer</label>
            <select name="customer_id" class="input">
                <option value="">All</option>
                @foreach($customers as $c)
                    <option value="{{ $c->id }}" @selected(request('customer_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <button class="btn-secondary">Filter</button>
        <a href="{{ route('sales.index') }}" class="btn-ghost">Reset</a>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-5 py-3 text-left">SO Number</th>
                <th class="px-5 py-3 text-left">Customer</th>
                <th class="px-5 py-3 text-left">Date</th>
                <th class="px-5 py-3 text-right">Total</th>
                <th class="px-5 py-3 text-right">Balance Due</th>
                <th class="px-5 py-3 text-center">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($salesOrders as $so)
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 font-mono text-xs font-medium text-gray-700">{{ $so->so_number }}</td>
                <td class="px-5 py-3">{{ $so->customer->name }}</td>
                <td class="px-5 py-3 text-gray-500">{{ $so->order_date->format('d M Y') }}</td>
                <td class="px-5 py-3 text-right font-medium">{{ number_format($so->total_amount, 2) }}</td>
                <td class="px-5 py-3 text-right {{ $so->amountDue() > 0 ? 'text-red-600 font-bold' : 'text-green-600' }}">
                    {{ number_format($so->amountDue(), 2) }}
                </td>
                <td class="px-5 py-3 text-center">
                    @php $colors = ['draft'=>'gray','confirmed'=>'blue','partial'=>'yellow','delivered'=>'green','cancelled'=>'red']; @endphp
                    <span class="px-2 py-0.5 rounded-full text-xs bg-{{ $colors[$so->status] ?? 'gray' }}-100 text-{{ $colors[$so->status] ?? 'gray' }}-700">
                        {{ ucfirst($so->status) }}
                    </span>
                </td>
                <td class="px-5 py-3 text-right">
                    <a href="{{ route('sales.show', $so) }}" class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-5 py-10 text-center text-gray-400">No sales orders found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-5 py-4 border-t border-gray-100">{{ $salesOrders->links() }}</div>
</div>
@endsection
