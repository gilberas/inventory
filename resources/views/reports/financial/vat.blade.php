@extends('layouts.app')

@section('title', 'VAT Report')

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
            <a href="{{ route('reports.financial.export.pdf', ['report' => 'vat'] + request()->query()) }}"
               class="btn btn-outline-danger btn-sm">PDF</a>
            <a href="{{ route('reports.financial.export.excel', ['report' => 'vat'] + request()->query()) }}"
               class="btn btn-outline-success btn-sm">Excel</a>
        </div>
    </form>

    {{-- TRA Header --}}
    <div class="card mb-4 border-primary">
        <div class="card-body">
            <div class="row">
                <div class="col-md-8">
                    <h5 class="fw-bold">{{ $tenant->name ?? 'N/A' }}</h5>
                    <p class="mb-1"><strong>TIN:</strong> {{ $tenant->tin ?? ($tenant->config['tin'] ?? 'N/A') }}</p>
                    <p class="mb-1"><strong>Report Period:</strong> {{ $startDate }} to {{ $endDate }}</p>
                    <p class="mb-0"><strong>Reference:</strong> {{ $sequentialRef }}</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <p class="mb-1 text-muted small">Generated: {{ now()->format('d M Y H:i') }}</p>
                    <span class="badge bg-primary fs-6">TRA-Compliant VAT Return</span>
                </div>
            </div>
        </div>
    </div>

    <h4 class="mb-4">VAT Report &mdash; {{ $startDate }} to {{ $endDate }}</h4>

    {{-- Summary --}}
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card mb-4">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-dark">
                            <tr><th>Description</th><th class="text-end">Amount (TZS)</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>VAT Collected (Output Tax)</td>
                                <td class="text-end text-success fw-semibold">{{ number_format($data['vatCollected'], 2) }}</td>
                            </tr>
                            <tr>
                                <td>VAT Paid on Purchases (Input Tax)</td>
                                <td class="text-end text-danger fw-semibold">({{ number_format($data['vatPaid'], 2) }})</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-warning fw-bold">
                            <tr>
                                <td>Net VAT Payable to TRA</td>
                                <td class="text-end fs-5">{{ number_format($data['netVatPayable'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- AC-1: Breakdown by tax rate --}}
    @if(collect($data['collectedByRate'] ?? [])->isNotEmpty() || collect($data['paidByRate'] ?? [])->isNotEmpty())
    <div class="row mt-2">
        {{-- Collected breakdown --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Output VAT Breakdown by Rate</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tax Rate</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">VAT Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['collectedByRate'] ?? collect() as $row)
                            @php $row = (object) $row; @endphp
                            <tr>
                                <td>{{ number_format($row->tax_rate, 1) }}%</td>
                                <td class="text-end">{{ number_format($row->taxable_amount, 2) }}</td>
                                <td class="text-end text-success fw-semibold">{{ number_format($row->vat_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No taxable sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Paid breakdown --}}
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header fw-semibold">Input VAT Breakdown by Rate</div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tax Rate</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">VAT Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['paidByRate'] ?? collect() as $row)
                            @php $row = (object) $row; @endphp
                            <tr>
                                <td>{{ number_format($row->tax_rate, 1) }}%</td>
                                <td class="text-end">{{ number_format($row->taxable_amount, 2) }}</td>
                                <td class="text-end text-danger fw-semibold">{{ number_format($row->vat_amount, 2) }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="3" class="text-center text-muted py-3">No taxable purchases in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
