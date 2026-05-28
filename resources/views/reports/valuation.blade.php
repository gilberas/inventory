@extends('layouts.dashboard')
@section('title', 'Inventory Valuation')
@section('breadcrumb', 'Reports / Valuation')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

{{-- Filter --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div style="flex:1;min-width:180px;">
            <label style="display:block;margin-bottom:.4rem;">Warehouse</label>
            <select name="warehouse_id" style="width:100%;">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="{{ route('reports.valuation') }}" class="btn btn-secondary btn-sm">Clear</a>
    </form>
</div>

{{-- Summary totals --}}
<div class="stats-grid" style="margin-bottom:1.25rem;">
    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
        <div>
            <div class="stat-value">${{ number_format($totalCost, 2) }}</div>
            <div class="stat-label">Total Cost Value</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-tag"></i></div>
        <div>
            <div class="stat-value">${{ number_format($totalSell, 2) }}</div>
            <div class="stat-label">Total Selling Value</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon sky"><i class="fas fa-chart-line"></i></div>
        <div>
            <div class="stat-value" style="color:var(--success);">${{ number_format($totalSell - $totalCost, 2) }}</div>
            <div class="stat-label">Potential Gross Profit</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-percent"></i></div>
        <div>
            <div class="stat-value">
                {{ $totalCost > 0 ? number_format((($totalSell - $totalCost) / $totalCost) * 100, 1) : 0 }}%
            </div>
            <div class="stat-label">Gross Margin</div>
        </div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Valuation by Product × Warehouse</span>
        <span style="color:var(--muted);font-size:.85rem;">{{ $data->count() }} records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Warehouse</th>
                    <th style="text-align:right;">Qty</th>
                    <th style="text-align:right;">Cost Price</th>
                    <th style="text-align:right;">Sell Price</th>
                    <th style="text-align:right;">Cost Value</th>
                    <th style="text-align:right;">Sell Value</th>
                    <th style="text-align:right;">Profit</th>
                </tr>
            </thead>
            <tbody>
                @forelse($data as $row)
                <tr>
                    <td><span style="font-family:monospace;font-size:.8rem;color:var(--muted);">{{ $row['sku'] }}</span></td>
                    <td style="font-weight:500;">{{ $row['product'] }}</td>
                    <td style="color:var(--muted);">{{ $row['category'] }}</td>
                    <td>{{ $row['warehouse'] }}</td>
                    <td style="text-align:right;">{{ number_format($row['qty'], 2) }}</td>
                    <td style="text-align:right;">${{ number_format($row['cost_price'], 2) }}</td>
                    <td style="text-align:right;">${{ number_format($row['selling_price'], 2) }}</td>
                    <td style="text-align:right;font-weight:600;">${{ number_format($row['total_cost_value'], 2) }}</td>
                    <td style="text-align:right;font-weight:600;color:var(--success);">${{ number_format($row['total_sell_value'], 2) }}</td>
                    <td style="text-align:right;font-weight:700;color:{{ ($row['total_sell_value'] - $row['total_cost_value']) >= 0 ? 'var(--success)' : 'var(--danger)' }};">
                        ${{ number_format($row['total_sell_value'] - $row['total_cost_value'], 2) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-coins"></i>
                            <h3>No valuation data</h3>
                            <p>No stock balances found for the selected filter.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
