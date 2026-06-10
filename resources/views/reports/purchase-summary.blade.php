@extends('layouts.dashboard')
@section('title', 'Purchase Summary')
@section('breadcrumb', 'Reports / Purchase Summary')

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
    <div class="card-header"><h2 class="card-title">Purchase Summary by Supplier</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Supplier</th><th>Orders</th><th>Total Value (TZS)</th><th>Avg Lead Time</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr><td><strong>{{ $r->supplier_name }}</strong></td><td>{{ $r->order_count }}</td><td>{{ number_format($r->total_value, 2) }}</td><td>{{ $r->avg_lead_time ? round($r->avg_lead_time) . ' days' : '—' }}</td></tr>
            @empty<tr><td colspan="4" style="text-align:center;color:var(--muted)">No data.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
