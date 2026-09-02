@extends('layouts.dashboard')
@section('title', 'Daily Sales')
@section('breadcrumb', 'Reports / Daily Sales')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
@endsection

@section('content')
{{-- Filters --}}
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>Date</label><input type="date" name="date" value="{{ $date }}"></div>
            <div><label>Branch</label>
                <select name="branch_id"><option value="">All</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div><label>Cashier</label>
                <select name="cashier_id"><option value="">All</option>
                @foreach($cashiers as $c)<option value="{{ $c->id }}" {{ $cashierId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

{{-- Totals --}}
<div class="stats-grid" style="margin-bottom:1rem">
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-cash-register"></i></div>
        <div><div class="stat-value">{{ number_format($data['totals']->revenue ?? 0, 2) }}</div><div class="stat-label">Revenue (TZS)</div></div>
    </div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value">{{ $data['totals']->transactions ?? 0 }}</div><div class="stat-label">Transactions</div></div>
    </div>
    <div class="stat-card"><div class="stat-icon amber"><i class="fas fa-percent"></i></div>
        <div><div class="stat-value">{{ number_format($data['totals']->discounts ?? 0, 2) }}</div><div class="stat-label">Discounts</div></div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    {{-- By Product --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Sales by Product</h2></div>
        <div class="table-wrapper"><table>
            <thead><tr><th>Product</th><th>SKU</th><th>Units</th><th>Revenue</th></tr></thead>
            <tbody>
                @forelse($data['byProduct'] as $r)
                <tr><td>{{ $r->name }}</td><td style="font-size:.8rem;color:var(--muted)">{{ $r->sku }}</td><td>{{ $r->units_sold }}</td><td>{{ number_format($r->revenue, 2) }}</td></tr>
                @empty<tr><td colspan="4" style="text-align:center;color:var(--muted)">No sales</td></tr>@endforelse
            </tbody>
        </table></div>
    </div>
    {{-- By Payment --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">By Payment Method</h2></div>
        <div class="table-wrapper"><table>
            <thead><tr><th>Method</th><th>Total</th><th>Count</th></tr></thead>
            <tbody>
                @forelse($data['byPayment'] as $r)
                <tr><td>{{ strtoupper($r->payment_method) }}</td><td>{{ number_format($r->total, 2) }}</td><td>{{ $r->count }}</td></tr>
                @empty<tr><td colspan="3" style="text-align:center;color:var(--muted)">No data</td></tr>@endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
