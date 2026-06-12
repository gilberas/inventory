@extends('layouts.app')

@section('title', 'Requisition #' . $requisition->id)

@section('content')
<div class="min-h-screen bg-gray-50" style="max-width:480px;margin:0 auto;">

    {{-- Header --}}
    <div class="bg-white border-b px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
        <a href="{{ route('requisitions.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="flex-1">
            <h1 class="text-lg font-semibold text-gray-900">Requisition #{{ $requisition->id }}</h1>
            <p class="text-xs text-gray-500">{{ $requisition->created_at->format('d M Y, H:i') }}</p>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold
            @if($requisition->status === 'approved') bg-green-100 text-green-800
            @elseif($requisition->status === 'rejected') bg-red-100 text-red-800
            @elseif($requisition->status === 'pending') bg-yellow-100 text-yellow-800
            @elseif($requisition->status === 'revision_requested') bg-orange-100 text-orange-800
            @else bg-gray-100 text-gray-800
            @endif">
            {{ ucfirst(str_replace('_', ' ', $requisition->status)) }}
        </span>
    </div>

    @if(session('success'))
    <div class="mx-4 mt-4 bg-green-50 border border-green-200 rounded-lg px-4 py-3 text-green-800 text-sm">
        {{ session('success') }}
    </div>
    @endif

    {{-- Requester info --}}
    <div class="bg-white mx-4 mt-4 rounded-xl shadow-sm p-4">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                <span class="text-blue-600 font-semibold text-sm">{{ strtoupper(substr($requisition->requestedBy?->name ?? '?', 0, 2)) }}</span>
            </div>
            <div>
                <p class="font-medium text-gray-900">{{ $requisition->requestedBy?->name ?? 'Unknown' }}</p>
                <p class="text-xs text-gray-500">{{ $requisition->branch?->name ?? 'No branch' }}</p>
            </div>
        </div>
        @if($requisition->notes)
        <p class="mt-3 text-sm text-gray-600 bg-gray-50 rounded-lg p-3">{{ $requisition->notes }}</p>
        @endif
    </div>

    {{-- Items --}}
    <div class="mx-4 mt-4">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Items ({{ $requisition->items->count() }})</h2>
        <div class="space-y-2">
            @foreach($requisition->items as $item)
            <div class="bg-white rounded-xl shadow-sm p-4">
                <div class="flex justify-between items-start">
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 truncate">{{ $item->product?->name ?? 'Unknown product' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">SKU: {{ $item->product?->sku ?? '—' }}</p>
                    </div>
                    <div class="text-right ml-4">
                        <p class="text-lg font-bold text-blue-600">{{ number_format($item->qty_requested, 2) }}</p>
                        <p class="text-xs text-gray-500">{{ $item->product?->unit?->abbreviation ?? 'units' }}</p>
                    </div>
                </div>
                @if($item->suggested_supplier_id)
                <p class="mt-2 text-xs text-gray-500">Supplier: {{ $item->suggestedSupplier?->name }}</p>
                @endif
                @if($item->notes)
                <p class="mt-1 text-xs text-gray-400 italic">{{ $item->notes }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Generate PO button (approved only) --}}
    @if($requisition->status === \App\Models\PurchaseRequisition::STATUS_APPROVED)
    <div class="mx-4 mt-4">
        <a href="{{ route('purchases.create', ['requisition_id' => $requisition->id]) }}"
           class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-semibold py-4 rounded-2xl shadow-sm transition text-base">
            Generate Purchase Order
        </a>
    </div>
    @endif

    {{-- Resubmit button (revision_requested — requester only) --}}
    @if($requisition->status === \App\Models\PurchaseRequisition::STATUS_REVISION_REQUESTED && auth()->id() === $requisition->requested_by)
    <div class="mx-4 mt-4 bg-orange-50 border border-orange-200 rounded-xl p-4">
        <p class="text-sm text-orange-800 font-medium mb-1">Revision Requested</p>
        <p class="text-xs text-orange-600 mb-3">Please review your requisition and resubmit once changes are made.</p>
        <form method="POST" action="{{ route('requisitions.resubmit', $requisition) }}">
            @csrf
            <button type="submit"
                    class="w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-4 rounded-2xl shadow-sm transition text-base">
                Resubmit for Approval
            </button>
        </form>
    </div>
    @endif

    {{-- Manager action buttons (pending only) --}}
    @can('purchase_orders.manage')
    @if($requisition->status === \App\Models\PurchaseRequisition::STATUS_PENDING)
    <div class="mx-4 mt-6 space-y-3 pb-6">

        {{-- Approve --}}
        <form method="POST" action="{{ route('requisitions.approve', $requisition) }}">
            @csrf
            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800 text-white font-bold py-5 rounded-2xl shadow-md transition text-lg tracking-wide">
                Approve
            </button>
        </form>

        {{-- Request Revision --}}
        <button type="button" onclick="document.getElementById('revise-panel').classList.toggle('hidden')"
                class="w-full bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white font-bold py-5 rounded-2xl shadow-md transition text-lg tracking-wide">
            Request Revision
        </button>
        <div id="revise-panel" class="hidden bg-orange-50 border border-orange-200 rounded-xl p-4">
            <form method="POST" action="{{ route('requisitions.revise', $requisition) }}">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for revision</label>
                <textarea name="reason" rows="3" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-orange-500 focus:border-orange-500"
                          placeholder="Explain what needs to be changed..."></textarea>
                <button type="submit" class="mt-3 w-full bg-orange-500 hover:bg-orange-600 text-white font-semibold py-3 rounded-xl transition text-sm">
                    Send Revision Request
                </button>
            </form>
        </div>

        {{-- Reject --}}
        <button type="button" onclick="document.getElementById('reject-panel').classList.toggle('hidden')"
                class="w-full bg-red-600 hover:bg-red-700 active:bg-red-800 text-white font-bold py-5 rounded-2xl shadow-md transition text-lg tracking-wide">
            Reject
        </button>
        <div id="reject-panel" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
            <form method="POST" action="{{ route('requisitions.reject', $requisition) }}">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-1">Reason for rejection</label>
                <textarea name="reason" rows="3" required
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-red-500 focus:border-red-500"
                          placeholder="State the reason for rejection..."></textarea>
                <button type="submit" class="mt-3 w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-xl transition text-sm">
                    Confirm Rejection
                </button>
            </form>
        </div>

    </div>
    @endif
    @endcan

    <div class="pb-8"></div>
</div>
@endsection
