@extends('layouts.dashboard')
@section('title', 'GRN vs Ordered')
@section('breadcrumb', 'Reports / GRN vs Ordered')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>From</label><input type="date" name="start_date" value="{{ $startDate }}"></div>
        <div><label>To</label><input type="date" name="end_date" value="{{ $endDate }}"></div>
        <div><label>Supplier</label><select name="supplier_id"><option value="">All</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">GRN vs Ordered</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>PO Ref</th><th>Supplier</th><th>Product</th><th>SKU</th><th>Ordered</th><th>Received</th><th>Variance</th><th>Fulfillment%</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->po_ref }}</td>
                <td>{{ $r->supplier_name }}</td>
                <td>{{ $r->product_name }}</td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->sku }}</td>
                <td>{{ $r->quantity_ordered }}</td>
                <td>{{ $r->quantity_received }}</td>
                <td style="color:{{ $r->variance > 0 ? 'var(--warning)' : 'var(--accent)' }}">{{ $r->variance }}</td>
                <td style="font-weight:600">{{ $r->fulfillment_pct }}%</td>
            </tr>
            @empty<tr><td colspan="8" style="text-align:center;color:var(--muted)">No data.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
