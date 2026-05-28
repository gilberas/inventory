@extends('layouts.app')
@section('title', $purchase->po_number)
@section('header', 'PO: ' . $purchase->po_number)

@section('header-actions')
    @if(in_array($purchase->status, ['ordered','partial']))
        <a href="{{ route('purchases.receive', $purchase) }}" class="btn-primary"><i class="fas fa-truck-loading mr-1"></i> Receive Goods</a>
    @endif
@endsection

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-5">
        <div class="bg-white rounded-xl shadow-sm p-6 grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-gray-400">Supplier</p><p class="font-medium">{{ $purchase->supplier->name }}</p></div>
            <div><p class="text-gray-400">Warehouse</p><p>{{ $purchase->warehouse->name }}</p></div>
            <div><p class="text-gray-400">Order Date</p><p>{{ $purchase->order_date->format('d M Y') }}</p></div>
            <div><p class="text-gray-400">Expected Date</p><p>{{ $purchase->expected_date?->format('d M Y') ?? '—' }}</p></div>
            <div><p class="text-gray-400">Created By</p><p>{{ $purchase->user->name ?? '—' }}</p></div>
            <div><p class="text-gray-400">Status</p>
                <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ ucfirst($purchase->status) }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b font-semibold text-gray-800">Order Items</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Product</th>
                        <th class="px-5 py-3 text-right">Ordered</th>
                        <th class="px-5 py-3 text-right">Received</th>
                        <th class="px-5 py-3 text-right">Remaining</th>
                        <th class="px-5 py-3 text-right">Unit Cost</th>
                        <th class="px-5 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($purchase->items as $item)
                    <tr>
                        <td class="px-5 py-3">{{ $item->product->name }}</td>
                        <td class="px-5 py-3 text-right">{{ $item->quantity_ordered }}</td>
                        <td class="px-5 py-3 text-right text-green-700">{{ $item->quantity_received }}</td>
                        <td class="px-5 py-3 text-right {{ $item->remaining_quantity > 0 ? 'text-yellow-600' : 'text-gray-400' }}">{{ $item->remaining_quantity }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($item->unit_cost, 2) }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-medium">
                    <tr><td colspan="5" class="px-5 py-3 text-right">Subtotal</td><td class="px-5 py-3 text-right">{{ number_format($purchase->subtotal, 2) }}</td></tr>
                    <tr><td colspan="5" class="px-5 py-3 text-right">Tax</td><td class="px-5 py-3 text-right">{{ number_format($purchase->tax_amount, 2) }}</td></tr>
                    <tr><td colspan="5" class="px-5 py-3 text-right">Discount</td><td class="px-5 py-3 text-right text-red-600">-{{ number_format($purchase->discount_amount, 2) }}</td></tr>
                    <tr class="text-base"><td colspan="5" class="px-5 py-3 text-right font-bold">Total</td><td class="px-5 py-3 text-right font-bold">{{ number_format($purchase->total_amount, 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="space-y-5">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Goods Receipts</h3>
            @forelse($purchase->goodsReceipts as $gr)
            <div class="text-sm border border-gray-100 rounded-lg p-3 mb-2">
                <p class="font-mono text-xs text-gray-500">{{ $gr->receipt_number }}</p>
                <p class="text-gray-700">{{ $gr->received_date->format('d M Y') }}</p>
                <p class="text-xs text-gray-400">by {{ $gr->receiver->name ?? '—' }}</p>
            </div>
            @empty
            <p class="text-sm text-gray-400">No receipts yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
