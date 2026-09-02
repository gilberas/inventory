@extends('layouts.dashboard')
@section('title', 'Dead Stock')
@section('breadcrumb', 'Reports / Dead Stock')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>No movement for (days)</label><input type="number" name="days_no_movement" value="{{ $days }}" min="1" style="width:100px"></div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-box" style="color:var(--muted)"></i> Dead Stock (&gt;{{ $days }} days no movement) — {{ $rows->count() }} items</h2>
    </div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Product</th><th>SKU</th><th>Warehouse</th><th>Qty</th><th>Value at Cost</th><th>Last Movement</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td><strong>{{ $r->name }}</strong></td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->sku }}</td>
                <td>{{ $r->warehouse_name }}</td>
                <td>{{ number_format($r->quantity, 2) }}</td>
                <td style="font-weight:600">{{ number_format($r->value_at_cost, 2) }}</td>
                <td style="color:var(--muted);font-size:.85rem">{{ $r->last_movement_date ? \Carbon\Carbon::parse($r->last_movement_date)->format('d M Y') : 'Never' }}</td>
            </tr>
            @empty<tr><td colspan="6" style="text-align:center;color:var(--muted)">No dead stock found.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
