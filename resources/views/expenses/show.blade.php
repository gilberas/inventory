@extends('layouts.app')

@section('title', 'Expense #' . $expense->id)

@section('content')
<div class="max-w-2xl mx-auto py-6 px-4">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('expenses.index') }}" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900">Expense #{{ $expense->id }}</h1>
            <span class="px-3 py-1 rounded-full text-xs font-semibold
                @if($expense->status === 'approved') bg-green-100 text-green-800
                @elseif($expense->status === 'rejected') bg-red-100 text-red-800
                @elseif($expense->status === 'pending_approval') bg-yellow-100 text-yellow-800
                @else bg-gray-100 text-gray-800
                @endif">
                {{ ucfirst(str_replace('_', ' ', $expense->status)) }}
            </span>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('expenses.export-pdf', $expense) }}"
               class="inline-flex items-center gap-1 bg-red-50 hover:bg-red-100 text-red-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                PDF
            </a>
            <a href="{{ route('expenses.export-excel', $expense) }}"
               class="inline-flex items-center gap-1 bg-green-50 hover:bg-green-100 text-green-700 text-sm font-medium px-3 py-2 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Excel
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-800 text-sm mb-4">{{ session('success') }}</div>
    @endif

    {{-- Rejection reason banner --}}
    @if($expense->status === 'rejected' && $expense->notes)
    <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
        <p class="text-sm font-semibold text-red-800">Rejection Reason</p>
        <p class="text-sm text-red-700 mt-1">{{ $expense->notes }}</p>
    </div>
    @endif

    {{-- Main details --}}
    <div class="bg-white rounded-xl shadow-sm divide-y divide-gray-100">

        <div class="px-5 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Category</p>
            <p class="text-base font-semibold text-gray-900 mt-1">{{ $expense->category }}</p>
        </div>

        <div class="px-5 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Description</p>
            <p class="text-sm text-gray-700 mt-1">{{ $expense->description }}</p>
        </div>

        <div class="grid grid-cols-2 divide-x divide-gray-100">
            <div class="px-5 py-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Amount</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">TZS {{ number_format($expense->amount, 2) }}</p>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Date</p>
                <p class="text-base font-semibold text-gray-900 mt-1">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</p>
            </div>
        </div>

        @if($expense->branch)
        <div class="px-5 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Branch</p>
            <p class="text-sm text-gray-900 mt-1">{{ $expense->branch->name }}</p>
        </div>
        @endif

        @if($expense->notes && $expense->status !== 'rejected')
        <div class="px-5 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Notes</p>
            <p class="text-sm text-gray-700 mt-1">{{ $expense->notes }}</p>
        </div>
        @endif

        @if($expense->receipt_path)
        <div class="px-5 py-4">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Receipt</p>
            <a href="{{ Storage::url($expense->receipt_path) }}" target="_blank"
               class="inline-flex items-center gap-1 mt-1 text-blue-600 hover:text-blue-700 text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                </svg>
                View Receipt
            </a>
        </div>
        @endif
    </div>

    {{-- Audit trail --}}
    <div class="bg-white rounded-xl shadow-sm mt-4 divide-y divide-gray-100">
        <div class="px-5 py-3 bg-gray-50 rounded-t-xl">
            <h2 class="text-sm font-semibold text-gray-700">Audit Trail</h2>
        </div>

        <div class="px-5 py-4 grid grid-cols-2 gap-4 text-sm">
            <div>
                <p class="text-xs text-gray-400 font-medium">Created By</p>
                <p class="text-gray-900 mt-0.5">{{ $expense->createdBy?->name ?? '—' }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $expense->created_at->format('d M Y, H:i') }}</p>
            </div>

            @if($expense->approved_at || $expense->approvedBy)
            <div>
                <p class="text-xs text-gray-400 font-medium">
                    {{ $expense->status === 'rejected' ? 'Rejected By' : 'Approved By' }}
                </p>
                <p class="text-gray-900 mt-0.5">{{ $expense->approvedBy?->name ?? '—' }}</p>
                @if($expense->approved_at)
                <p class="text-xs text-gray-400 mt-0.5">{{ $expense->approved_at->format('d M Y, H:i') }}</p>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Actions for pending expenses --}}
    @if($expense->status === 'pending_approval')
    @can('expenses.manage')
    <div class="mt-4 space-y-3">
        <form method="POST" action="{{ route('expenses.approve', $expense) }}">
            @csrf
            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 rounded-xl transition">
                Approve Expense
            </button>
        </form>

        <details class="bg-white rounded-xl shadow-sm">
            <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-red-600 hover:text-red-700">
                Reject Expense
            </summary>
            <form method="POST" action="{{ route('expenses.reject', $expense) }}" class="px-4 pb-4">
                @csrf
                <textarea name="reason" rows="3" required
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm mt-2"
                          placeholder="Reason for rejection..."></textarea>
                <button type="submit" class="w-full mt-2 bg-red-600 hover:bg-red-700 text-white font-semibold py-3 rounded-xl transition text-sm">
                    Confirm Rejection
                </button>
            </form>
        </details>
    </div>
    @endcan
    @endif

</div>
@endsection
