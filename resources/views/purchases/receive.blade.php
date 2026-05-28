@extends('layouts.app')
@section('title', 'Receive Goods')
@section('header', 'Receive Goods — ' . $purchase->po_number)

@section('content')
<form method="POST" action="{{ route('purchases.storeReceipt', $purchase) }}" class="space-y-5 max-w-3xl">
    @csrf
    <div class="bg-white rounded-xl shadow-sm p-6 grid grid-cols-2 gap-4">
        <div>
            <label class="label">Received Date *</label>
            <input type="date" name="received_date" value="{{ date('Y-m-d') }}" class="input" required>
        </div>
        <div>
            <label class="label">Notes</label>
            <input type="text" name="notes" class="input" placeholder="Optional notes…">
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b font-semibold text-gray-800">Items to Receive</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-5 py-3 text-left">Product</th>
                    <th class="px-5 py-3 text-right">Ordered</th>
                    <th class="px-5 py-3 text-right">Already Received</th>
                    <th class="px-5 py-3 text-right">Remaining</th>
                    <th class="px-5 py-3 text-right">Receive Now</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($purchase->items as $i => $item)
                <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $item->id }}">
                <tr class="{{ $item->remaining_quantity <= 0 ? 'opacity-50' : '' }}">
                    <td class="px-5 py-3">{{ $item->product->name }}</td>
                    <td class="px-5 py-3 text-right">{{ $item->quantity_ordered }} {{ $item->product->unit->abbreviation }}</td>
                    <td class="px-5 py-3 text-right text-green-700">{{ $item->quantity_received }}</td>
                    <td class="px-5 py-3 text-right font-medium {{ $item->remaining_quantity > 0 ? 'text-yellow-600' : 'text-gray-400' }}">{{ $item->remaining_quantity }}</td>
                    <td class="px-5 py-3 text-right">
                        <input type="number" name="items[{{ $i }}][quantity_received]"
                               value="{{ $item->remaining_quantity }}"
                               min="0" max="{{ $item->remaining_quantity }}" step="0.01"
                               class="input text-right w-28"
                               {{ $item->remaining_quantity <= 0 ? 'disabled' : '' }}>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary"><i class="fas fa-check mr-1"></i> Confirm Receipt</button>
        <a href="{{ route('purchases.show', $purchase) }}" class="btn-ghost">Cancel</a>
    </div>
</form>
@endsection
