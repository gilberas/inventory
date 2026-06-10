@extends('layouts.dashboard')
@section('title', 'Customer History')
@section('breadcrumb', 'Reports / Customer History')

@section('topbar-actions')
    @if($customerId)
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
    @endif
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>Customer</label>
            <select name="customer_id">
                <option value="">Choose customer...</option>
                @foreach($customers as $c)<option value="{{ $c->id }}" {{ $customerId == $c->id ? 'selected' : '' }}>{{ $c->name }} ({{ $c->code }})</option>@endforeach
            </select>
        </div>
        <div><label>From</label><input type="date" name="start_date" value="{{ $startDate }}"></div>
        <div><label>To</label><input type="date" name="end_date" value="{{ $endDate }}"></div>
        <button type="submit" class="btn btn-primary btn-sm">View</button>
    </form>
</div></div>

@if($customer && $stats)
<div class="stats-grid" style="margin-bottom:1rem">
    <div class="stat-card"><div class="stat-icon green"><i class="fas fa-coins"></i></div>
        <div><div class="stat-value">{{ number_format($stats['total_spend'], 2) }}</div><div class="stat-label">Total Spend (TZS)</div></div>
    </div>
    <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
        <div><div class="stat-value">{{ $stats['transaction_count'] }}</div><div class="stat-label">Transactions</div></div>
    </div>
    <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-chart-bar"></i></div>
        <div><div class="stat-value">{{ number_format($stats['avg_order_value'], 2) }}</div><div class="stat-label">Avg Order Value</div></div>
    </div>
</div>
@endif

@if($customerId)
<div class="card">
    <div class="card-header"><h2 class="card-title">{{ $customer?->name ?? 'Customer' }} — Purchase History</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Receipt No</th><th>Payment Method</th><th>Total (TZS)</th><th>Discount</th><th>Date</th></tr></thead>
        <tbody>
            @forelse($sales as $s)
            <tr>
                <td style="font-family:monospace;font-size:.8rem">{{ $s->receipt_no }}</td>
                <td>{{ strtoupper($s->payment_method) }}</td>
                <td>{{ number_format($s->grand_total, 2) }}</td>
                <td>{{ number_format($s->discount, 2) }}</td>
                <td style="color:var(--muted)">{{ \Carbon\Carbon::parse($s->created_at)->format('d M Y H:i') }}</td>
            </tr>
            @empty<tr><td colspan="5" style="text-align:center;color:var(--muted)">No sales in this period.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@else
<div class="card"><div style="padding:2rem;text-align:center;color:var(--muted)">Select a customer above to view their purchase history.</div></div>
@endif
@endsection
