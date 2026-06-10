@extends('layouts.dashboard')
@section('title', 'Low Stock Report')
@section('breadcrumb', 'Reports / Low Stock')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}"   class="btn btn-secondary btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>Warehouse</label><select name="warehouse_id"><option value="">All</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" {{ $warehouseId == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>@endforeach</select></div>
            <div><label>Category</label><select name="category_id"><option value="">All</option>@foreach($categories as $c)<option value="{{ $c->id }}" {{ $categoryId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-triangle-exclamation" style="color:var(--warning)"></i> Low Stock — {{ $rows->count() }} products</h2>
    </div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Product</th><th>SKU</th><th>Warehouse</th><th>Current Qty</th><th>Reorder Level</th><th>Suggested Order</th><th>Supplier</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td><strong>{{ $r->name }}</strong><br><small style="color:var(--muted)">{{ $r->category }}</small></td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->sku }}</td>
                <td>{{ $r->warehouse_name }}</td>
                <td style="font-weight:600;color:var(--danger)">{{ number_format($r->current_qty, 2) }}</td>
                <td>{{ number_format($r->reorder_level, 2) }}</td>
                <td style="color:var(--accent)">{{ number_format($r->suggested_order_qty, 2) }}</td>
                <td style="color:var(--muted)">{{ $r->supplier_name ?? '—' }}</td>
            </tr>
            @empty<tr><td colspan="7" style="text-align:center;color:var(--muted)">No low-stock products.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
