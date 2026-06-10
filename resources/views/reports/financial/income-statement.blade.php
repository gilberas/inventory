@extends('layouts.app')

@section('title', 'Income Statement')

@section('content')
<div class="container-fluid py-4">
    {{-- Filter form --}}
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
            <a href="{{ route('reports.financial.export.pdf', ['report' => 'income-statement'] + request()->query()) }}"
               class="btn btn-outline-danger btn-sm">PDF</a>
            <a href="{{ route('reports.financial.export.excel', ['report' => 'income-statement'] + request()->query()) }}"
               class="btn btn-outline-success btn-sm">Excel</a>
        </div>
    </form>

    <h4 class="mb-4">Income Statement &mdash; {{ $startDate }} to {{ $endDate }}</h4>

    <div class="row">
        {{-- Revenue --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold">Revenue</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><td>Gross Revenue</td><td class="text-end">{{ number_format($data['revenue'], 2) }}</td></tr>
                        <tr><td>Sales Returns</td><td class="text-end text-danger">({{ number_format($data['returns'], 2) }})</td></tr>
                        <tr class="table-light fw-bold"><td>Net Revenue</td><td class="text-end">{{ number_format($data['netRevenue'], 2) }}</td></tr>
                        <tr><td>COGS</td><td class="text-end text-danger">({{ number_format($data['cogs'], 2) }})</td></tr>
                        <tr class="table-light fw-bold"><td>Gross Profit</td><td class="text-end">{{ number_format($data['grossProfit'], 2) }}</td></tr>
                        <tr><td>Gross Margin</td><td class="text-end">{{ $data['grossMargin'] }}%</td></tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Operating Expenses --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold">Operating Expenses</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        @forelse($data['opexByCategory'] as $category => $amount)
                        <tr><td>{{ $category }}</td><td class="text-end">{{ number_format($amount, 2) }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-muted text-center py-2">No approved expenses</td></tr>
                        @endforelse
                        <tr class="table-light fw-bold"><td>Total OpEx</td><td class="text-end">{{ number_format($data['totalOpex'], 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Net Profit --}}
    <div class="card border-{{ $data['netProfit'] >= 0 ? 'success' : 'danger' }}">
        <div class="card-body d-flex justify-content-between align-items-center">
            <span class="fs-5 fw-bold">Net Profit</span>
            <span class="fs-4 fw-bold text-{{ $data['netProfit'] >= 0 ? 'success' : 'danger' }}">
                TZS {{ number_format($data['netProfit'], 2) }}
            </span>
        </div>
    </div>
</div>
@endsection
