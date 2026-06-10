@extends('layouts.app')

@section('title', 'Balance Sheet')

@section('content')
<div class="container-fluid py-4">
    <form method="GET" class="row g-2 mb-4 align-items-end">
        <div class="col-auto">
            <label class="form-label fw-semibold">Period</label>
            <select name="period" class="form-select form-select-sm">
                @foreach(['month' => 'This Month', 'quarter' => 'This Quarter', 'year' => 'This Year'] as $val => $label)
                    <option value="{{ $val }}" @selected($period === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label fw-semibold">From</label>
            <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <label class="form-label fw-semibold">To</label>
            <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm">
        </div>
        <div class="col-auto">
            <button class="btn btn-primary btn-sm">Refresh</button>
        </div>
        <div class="col-auto ms-auto">
            <a href="{{ route('reports.financial.export.pdf', ['report' => 'balance-sheet'] + request()->query()) }}"
               class="btn btn-outline-danger btn-sm">PDF</a>
            <a href="{{ route('reports.financial.export.excel', ['report' => 'balance-sheet'] + request()->query()) }}"
               class="btn btn-outline-success btn-sm">Excel</a>
        </div>
    </form>

    <h4 class="mb-4">Balance Sheet &mdash; As at {{ $endDate }}</h4>

    <div class="row">
        {{-- Assets --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold">Assets</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-secondary"><tr><th colspan="2">Inventory Value</th></tr></thead>
                        @forelse($data['inventoryByWarehouse'] as $warehouse => $value)
                        <tr><td class="ps-4">{{ $warehouse }}</td><td class="text-end">{{ number_format($value, 2) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-muted text-center">No inventory</td></tr>
                        @endforelse
                        <tr class="fw-semibold"><td>Total Inventory</td><td class="text-end">{{ number_format($data['inventoryTotal'], 2) }}</td></tr>
                        <tr><td>Cash &amp; Equivalents</td><td class="text-end">{{ number_format($data['cashAssets'], 2) }}</td></tr>
                        <tr class="table-light fw-bold"><td>Total Assets</td><td class="text-end">{{ number_format($data['totalAssets'], 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Liabilities + Equity --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold">Liabilities &amp; Equity</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-secondary"><tr><th colspan="2">Liabilities</th></tr></thead>
                        <tr><td>Supplier Payables</td><td class="text-end">{{ number_format($data['payables'], 2) }}</td></tr>
                        <tr><td>Customer Advances</td><td class="text-end">{{ number_format($data['customerAdvances'], 2) }}</td></tr>
                        <tr class="fw-semibold"><td>Total Liabilities</td><td class="text-end">{{ number_format($data['totalLiabilities'], 2) }}</td></tr>
                        <thead class="table-secondary"><tr><th colspan="2">Equity</th></tr></thead>
                        <tr><td>Retained Earnings / Equity</td><td class="text-end">{{ number_format($data['equity'], 2) }}</td></tr>
                        <tr class="table-light fw-bold"><td>Total Liabilities + Equity</td>
                            <td class="text-end">{{ number_format($data['totalLiabilities'] + $data['equity'], 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @if(round($data['totalAssets'], 2) !== round($data['totalLiabilities'] + $data['equity'], 2))
    <div class="alert alert-warning">Balance sheet does not balance — check for unrecorded transactions.</div>
    @endif
</div>
@endsection
