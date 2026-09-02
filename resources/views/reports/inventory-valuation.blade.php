@extends('layouts.dashboard')
@section('title', 'Inventory Valuation')
@section('breadcrumb', 'Reports / Inventory Valuation')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"   class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>Warehouse</label><select name="warehouse_id"><option value="">All</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>@endforeach</select></div>
            <div><label>As of Date</label><input type="date" name="as_of_date" value="{{ $asOfDate }}"></div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<div class="stats-grid" style="margin-bottom:1rem">
    <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-coins"></i></div>
        <div><div class="stat-value">{{ number_format($grandTotal, 2) }}</div><div class="stat-label">Total Cost Value (TZS)</div></div>
    </div>
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-tag"></i></div>
        <div><div class="stat-value">{{ number_format($retailTotal, 2) }}</div><div class="stat-label">Total Retail Value (TZS)</div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Stock Valuation by Product</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Warehouse</th><th>Qty</th><th>Avg Cost</th><th>Total Value</th><th>Retail Value</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td><strong>{{ $r->name }}</strong></td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->sku }}</td>
                <td style="color:var(--muted)">{{ $r->category ?? '—' }}</td>
                <td>{{ $r->warehouse_name }}</td>
                <td>{{ number_format($r->qty, 2) }}</td>
                <td>{{ number_format($r->avg_cost, 4) }}</td>
                <td style="font-weight:600">{{ number_format($r->total_value, 2) }}</td>
                <td>{{ number_format($r->retail_value, 2) }}</td>
            </tr>
            @empty<tr><td colspan="8" style="text-align:center;color:var(--muted)">No inventory data.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
