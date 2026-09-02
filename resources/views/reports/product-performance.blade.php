@extends('layouts.dashboard')
@section('title', 'Product Performance')
@section('breadcrumb', 'Reports / Product Performance')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>From</label><input type="date" name="start_date" value="{{ $startDate }}"></div>
            <div><label>To</label><input type="date" name="end_date" value="{{ $endDate }}"></div>
            <div><label>Branch</label><select name="branch_id"><option value="">All</option>@foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach</select></div>
            <div><label>Category</label><select name="category_id"><option value="">All</option>@foreach($categories as $c)<option value="{{ $c->id }}" {{ $categoryId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Product Performance</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Product</th><th>SKU</th><th>Units Sold</th><th>Revenue</th><th>COGS</th><th>Margin%</th></tr></thead>
        <tbody>
            @forelse($results as $r)
            <tr>
                <td><strong>{{ $r->name }}</strong></td>
                <td style="font-size:.8rem;color:var(--muted)">{{ $r->sku }}</td>
                <td>{{ number_format($r->units_sold, 2) }}</td>
                <td>{{ number_format($r->revenue, 2) }}</td>
                <td>{{ number_format($r->cogs, 2) }}</td>
                <td style="font-weight:600;color:{{ $r->margin_pct >= 20 ? 'var(--accent)' : 'var(--warning)' }}">{{ $r->margin_pct }}%</td>
            </tr>
            @empty<tr><td colspan="6" style="text-align:center;color:var(--muted)">No sales in this period.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
