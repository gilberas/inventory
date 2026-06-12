@extends('layouts.app')
@section('title', $sale->so_number)
@section('header', 'SO: ' . $sale->so_number)

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-5">
        <div class="bg-white rounded-xl shadow-sm p-6 grid grid-cols-2 gap-4 text-sm">
            <div><p class="text-gray-400">Customer</p><p class="font-medium">{{ $sale->customer->name }}</p></div>
            <div><p class="text-gray-400">Warehouse</p><p>{{ $sale->warehouse->name }}</p></div>
            <div><p class="text-gray-400">Order Date</p><p>{{ $sale->order_date->format('d M Y') }}</p></div>
            <div><p class="text-gray-400">Delivery Date</p><p>{{ $sale->delivery_date?->format('d M Y') ?? '—' }}</p></div>
            <div><p class="text-gray-400">Created By</p><p>{{ $sale->user->name ?? '—' }}</p></div>
            <div><p class="text-gray-400">Status</p>
                <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-700">{{ ucfirst($sale->status) }}</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b font-semibold text-gray-800">Items</div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Product</th>
                        <th class="px-5 py-3 text-right">Ordered</th>
                        <th class="px-5 py-3 text-right">Delivered</th>
                        <th class="px-5 py-3 text-right">Unit Price</th>
                        <th class="px-5 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($sale->items as $item)
                    <tr>
                        <td class="px-5 py-3">{{ $item->product->name }}</td>
                        <td class="px-5 py-3 text-right">{{ $item->quantity_ordered }}</td>
                        <td class="px-5 py-3 text-right text-green-700">{{ $item->quantity_delivered }}</td>
                        <td class="px-5 py-3 text-right">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="px-5 py-3 text-right font-medium">{{ number_format($item->subtotal, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-50 font-medium">
                    <tr><td colspan="4" class="px-5 py-3 text-right">Subtotal</td><td class="px-5 py-3 text-right">{{ number_format($sale->subtotal, 2) }}</td></tr>
                    <tr><td colspan="4" class="px-5 py-3 text-right">Tax</td><td class="px-5 py-3 text-right">{{ number_format($sale->tax_amount, 2) }}</td></tr>
                    <tr><td colspan="4" class="px-5 py-3 text-right">Discount</td><td class="px-5 py-3 text-right text-red-600">-{{ number_format($sale->discount_amount, 2) }}</td></tr>
                    <tr class="text-base"><td colspan="4" class="px-5 py-3 text-right font-bold">Total</td><td class="px-5 py-3 text-right font-bold">{{ number_format($sale->total_amount, 2) }}</td></tr>
                    <tr><td colspan="4" class="px-5 py-3 text-right text-green-600">Paid</td><td class="px-5 py-3 text-right text-green-600">{{ number_format($sale->amountPaid(), 2) }}</td></tr>
                    <tr><td colspan="4" class="px-5 py-3 text-right text-red-600 font-bold">Balance Due</td><td class="px-5 py-3 text-right text-red-600 font-bold">{{ number_format($sale->amountDue(), 2) }}</td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Sidebar: payments + add payment -->
    <div class="space-y-5">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Payments</h3>
            @forelse($sale->payments as $payment)
            <div class="flex items-center justify-between text-sm py-2 border-b border-gray-50 last:border-0">
                <div>
                    <p class="font-medium">{{ number_format($payment->amount, 2) }}</p>
                    <p class="text-xs text-gray-400">{{ $payment->payment_method }} · {{ $payment->payment_date->format('d M Y') }}</p>
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400">No payments yet.</p>
            @endforelse
        </div>

        @if($sale->amountDue() > 0)
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Add Payment</h3>
            <form method="POST" action="{{ route('sales.payment', $sale) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="label">Amount</label>
                    <input type="number" name="amount" value="{{ $sale->amountDue() }}" step="0.01" min="0.01" class="input" required>
                </div>
                <div>
                    <label class="label">Method</label>
                    <select name="payment_method" class="input">
                        <option>Cash</option><option>Bank Transfer</option><option>Cheque</option><option>Card</option>
                    </select>
                </div>
                <div>
                    <label class="label">Date</label>
                    <input type="date" name="payment_date" value="{{ date('Y-m-d') }}" class="input" required>
                </div>
                <div>
                    <label class="label">Reference</label>
                    <input type="text" name="reference" class="input" placeholder="Receipt or ref no.">
                </div>
                <button type="submit" class="btn-primary w-full">Record Payment</button>
            </form>
        </div>
        @endif
    </div>
</div>
@endsection
