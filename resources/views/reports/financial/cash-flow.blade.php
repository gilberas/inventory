@extends('layouts.app')

@section('title', 'Cash Flow Statement')

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
            <a href="{{ route('reports.financial.export.pdf', ['report' => 'cash-flow'] + request()->query()) }}"
               class="btn btn-outline-danger btn-sm">PDF</a>
            <a href="{{ route('reports.financial.export.excel', ['report' => 'cash-flow'] + request()->query()) }}"
               class="btn btn-outline-success btn-sm">Excel</a>
        </div>
    </form>

    <h4 class="mb-4">Cash Flow Statement &mdash; {{ $startDate }} to {{ $endDate }}</h4>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold text-success">Cash Inflows</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><td>Cash Sales Receipts</td><td class="text-end text-success">{{ number_format($data['cashIn'], 2) }}</td></tr>
                        <tr class="table-light fw-bold"><td>Total Cash In</td><td class="text-end text-success">{{ number_format($data['cashIn'], 2) }}</td></tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-bold text-danger">Cash Outflows</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <tr><td>Supplier Payments</td><td class="text-end text-danger">({{ number_format($data['supplierCashOut'], 2) }})</td></tr>
                        <tr><td>Expense Payments</td><td class="text-end text-danger">({{ number_format($data['expenseCashOut'], 2) }})</td></tr>
                        <tr class="table-light fw-bold"><td>Total Cash Out</td><td class="text-end text-danger">({{ number_format($data['cashOut'], 2) }})</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-{{ $data['netCash'] >= 0 ? 'success' : 'danger' }}">
        <div class="card-body d-flex justify-content-between align-items-center">
            <span class="fs-5 fw-bold">Net Cash Position</span>
            <span class="fs-4 fw-bold text-{{ $data['netCash'] >= 0 ? 'success' : 'danger' }}">
                TZS {{ number_format($data['netCash'], 2) }}
            </span>
        </div>
    </div>
</div>
@endsection
