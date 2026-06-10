@extends('layouts.dashboard')
@section('title', 'Employee Performance')
@section('breadcrumb', 'Reports / Employee Performance')

@section('topbar-actions')
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>From</label><input type="date" name="start_date" value="{{ $startDate }}"></div>
        <div><label>To</label><input type="date" name="end_date" value="{{ $endDate }}"></div>
        <div><label>Cashier</label><select name="cashier_id"><option value="">All</option>@foreach($cashiers as $c)<option value="{{ $c->id }}" {{ $cashierId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>@endforeach</select></div>
        <div><label>Branch</label><select name="branch_id"><option value="">All</option>@foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach</select></div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Cashier Performance</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Cashier</th><th>Transactions</th><th>Revenue (TZS)</th><th>Avg Transaction</th><th>Discounts</th><th>Returns</th></tr></thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td><strong>{{ $r->cashier_name }}</strong></td>
                <td>{{ $r->transactions }}</td>
                <td>{{ number_format($r->revenue, 2) }}</td>
                <td>{{ number_format($r->avg_transaction, 2) }}</td>
                <td>{{ number_format($r->discount_given, 2) }}</td>
                <td style="color:{{ $r->returns > 0 ? 'var(--danger)' : 'var(--muted)' }}">{{ $r->returns }}</td>
            </tr>
            @empty<tr><td colspan="6" style="text-align:center;color:var(--muted)">No data.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@endsection
