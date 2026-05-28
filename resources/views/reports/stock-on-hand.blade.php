@extends('layouts.dashboard')
@section('title', 'Stock on Hand')
@section('breadcrumb', 'Reports / Stock on Hand')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back to Reports
    </a>
@endsection

@section('content')

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div class="search-input" style="flex:2;min-width:200px;">
            <i class="fas fa-search"></i>
            <input name="search" value="{{ request('search') }}" placeholder="Search product name or SKU…">
        </div>
        <div style="flex:1;min-width:160px;">
            <select name="warehouse_id" style="width:100%;">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="{{ route('reports.stock') }}" class="btn btn-secondary btn-sm">Clear</a>
    </form>
</div>

{{-- Total value banner --}}
<div class="stat-card" style="margin-bottom:1.25rem;">
    <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
    <div>
        <div class="stat-value">${{ number_format($totalValue, 2) }}</div>
        <div class="stat-label">Total Stock Value (at cost price)</div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Stock on Hand — {{ $balances->total() }} records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Warehouse</th>
                    <th>Unit</th>
                    <th style="text-align:right;">Available</th>
                    <th style="text-align:right;">Reserved</th>
                    <th style="text-align:right;">Cost Price</th>
                    <th style="text-align:right;">Stock Value</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($balances as $balance)
                <tr>
                    <td><span style="font-family:monospace;font-size:.8rem;color:var(--muted);">{{ $balance->product->sku }}</span></td>
                    <td>
                        <a href="{{ route('products.show', $balance->product) }}" style="color:var(--primary);text-decoration:none;font-weight:500;">
                            {{ $balance->product->name }}
                        </a>
                    </td>
                    <td style="color:var(--muted);">{{ $balance->product->category?->name ?? '—' }}</td>
                    <td>{{ $balance->warehouse->name }}</td>
                    <td style="color:var(--muted);">{{ $balance->product->unit?->abbreviation ?? '—' }}</td>
                    <td style="text-align:right;font-weight:600;">
                        <span style="{{ $balance->quantity_available <= $balance->product->minimum_stock ? 'color:var(--danger)' : 'color:var(--success)' }}">
                            {{ number_format($balance->quantity_available, 2) }}
                        </span>
                    </td>
                    <td style="text-align:right;color:var(--muted);">{{ number_format($balance->quantity_reserved, 2) }}</td>
                    <td style="text-align:right;">${{ number_format($balance->product->cost_price, 2) }}</td>
                    <td style="text-align:right;font-weight:600;">${{ number_format($balance->quantity_available * $balance->product->cost_price, 2) }}</td>
                    <td>
                        @if($balance->quantity_available <= 0)
                            <span class="badge badge-red">Out of Stock</span>
                        @elseif($balance->quantity_available <= $balance->product->minimum_stock)
                            <span class="badge badge-amber">Low Stock</span>
                        @else
                            <span class="badge badge-green">In Stock</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-boxes-stacked"></i>
                            <h3>No stock records found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $balances->withQueryString()->links() }}
    </div>
</div>

@endsection
