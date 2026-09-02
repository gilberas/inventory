@extends('layouts.dashboard')
@section('title', 'Expense Breakdown')
@section('breadcrumb', 'Reports / Expense Breakdown')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>From</label><input type="date" name="start_date" value="{{ $startDate }}"></div>
        <div><label>To</label><input type="date" name="end_date" value="{{ $endDate }}"></div>
        <div><label>Branch</label><select name="branch_id"><option value="">All</option>@foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach</select></div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">Expense Breakdown</h2>
        <span style="font-size:.9rem;color:var(--muted)">Grand Total: <strong>{{ number_format($grandTotal, 2) }} TZS</strong></span>
    </div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Category</th><th>Total (TZS)</th><th>% of All</th><th>Budget (TZS)</th><th>Variance</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            @php $budget = $budgets[$r->category] ?? 0; $variance = $budget - $r->total; @endphp
            <tr>
                <td><strong>{{ $r->category }}</strong></td>
                <td>{{ number_format($r->total, 2) }}</td>
                <td>
                    <div style="display:flex;align-items:center;gap:.5rem">
                        <div style="height:8px;width:{{ min($r->pct * 2, 100) }}px;background:var(--primary);border-radius:4px"></div>
                        {{ $r->pct }}%
                    </div>
                </td>
                <td>{{ $budget > 0 ? number_format($budget, 2) : '—' }}</td>
                <td style="color:{{ $budget > 0 && $variance >= 0 ? 'var(--accent)' : 'var(--danger)' }}">
                    {{ $budget > 0 ? ($variance >= 0 ? '+' : '') . number_format($variance, 2) : '—' }}
                </td>
            </tr>
            @empty<tr><td colspan="5" style="text-align:center;color:var(--muted)">No approved expenses.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
