@extends('layouts.dashboard')
@section('title', 'Supplier Aging')
@section('breadcrumb', 'Reports / Supplier Aging')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>Supplier</label><select name="supplier_id"><option value="">All</option>@foreach($allSuppliers as $s)<option value="{{ $s->id }}" {{ $supplierId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>@endforeach</select></div>
        <div><label>As of Date</label><input type="date" name="as_of_date" value="{{ $asOfDate }}"></div>
        <button type="submit" class="btn btn-primary btn-sm">View</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Supplier Payables Aging (TZS)</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Supplier</th><th>Code</th><th>Current</th><th>1-30 Days</th><th>31-60 Days</th><th>60+ Days</th><th>Total</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td><strong>{{ $r['supplier'] }}</strong></td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r['code'] }}</td>
                <td>{{ number_format($r['current'], 2) }}</td>
                <td style="{{ $r['days_30'] > 0 ? 'color:var(--warning)' : '' }}">{{ number_format($r['days_30'], 2) }}</td>
                <td style="{{ $r['days_60'] > 0 ? 'color:var(--warning)' : '' }}">{{ number_format($r['days_60'], 2) }}</td>
                <td style="{{ $r['days_90_plus'] > 0 ? 'color:var(--danger);font-weight:600' : '' }}">{{ number_format($r['days_90_plus'], 2) }}</td>
                <td style="font-weight:600">{{ number_format($r['total'], 2) }}</td>
            </tr>
            @empty<tr><td colspan="7" style="text-align:center;color:var(--muted)">No outstanding payables.</td></tr>@endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr style="font-weight:600;border-top:2px solid var(--border)">
                <td colspan="2">TOTAL</td>
                <td>{{ number_format($rows->sum('current'), 2) }}</td>
                <td>{{ number_format($rows->sum('days_30'), 2) }}</td>
                <td>{{ number_format($rows->sum('days_60'), 2) }}</td>
                <td>{{ number_format($rows->sum('days_90_plus'), 2) }}</td>
                <td>{{ number_format($rows->sum('total'), 2) }}</td>
            </tr>
        </tfoot>
        @endif
    </table></div>
</div>
@endsection
