@extends('layouts.dashboard')
@section('title', 'Sales Trend')
@section('breadcrumb', 'Reports / Sales Trend')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem">
        <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            <div><label>Period</label>
                <select name="period">
                    <option value="weekly"  {{ $period === 'weekly'  ? 'selected' : '' }}>Weekly</option>
                    <option value="monthly" {{ $period === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="yearly"  {{ $period === 'yearly'  ? 'selected' : '' }}>Yearly</option>
                </select>
            </div>
            <div><label>Branch</label>
                <select name="branch_id"><option value="">All</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Sales Trend ({{ ucfirst($period) }})</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Period</th><th>Revenue (TZS)</th><th>Transactions</th></tr></thead>
        <tbody>
            @forelse($trend as $r)
            <tr><td style="font-family:monospace">{{ $r->period }}</td><td>{{ number_format($r->revenue, 2) }}</td><td>{{ $r->transactions }}</td></tr>
            @empty<tr><td colspan="3" style="text-align:center;color:var(--muted)">No data for the selected filters.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
