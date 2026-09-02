@extends('layouts.dashboard')
@section('title', 'Report Schedules')
@section('breadcrumb', 'Reports / Schedules')

@section('content')
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

{{-- Create schedule form --}}
<div class="card" style="margin-bottom:1rem">
    <div class="card-header"><h2 class="card-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Schedule a Report</h2></div>
    <div style="padding:1rem">
        <form method="POST" action="{{ route('reports.schedules.store') }}" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
            @csrf
            <div><label>Report Type *</label>
                <select name="report_type" required>
                    <option value="">Choose...</option>
                    @foreach([
                        'daily-sales' => 'Daily Sales',
                        'sales-trend' => 'Sales Trend',
                        'product-performance' => 'Product Performance',
                        'low-stock' => 'Low Stock',
                        'dead-stock' => 'Dead Stock',
                        'inventory-valuation' => 'Inventory Valuation',
                        'purchase-summary' => 'Purchase Summary',
                        'expense-breakdown' => 'Expense Breakdown',
                        'employee-performance' => 'Employee Performance',
                    ] as $v => $l)
                    <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div><label>Frequency *</label>
                <select name="frequency" required>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <div><label>Send To *</label><input type="email" name="email" placeholder="email@example.com" required></div>
            <button type="submit" class="btn btn-primary">Schedule</button>
        </form>
    </div>
</div>

{{-- Existing schedules --}}
<div class="card">
    <div class="card-header"><h2 class="card-title">Active Schedules</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Report</th><th>Frequency</th><th>Email</th><th>Last Sent</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($schedules as $s)
            <tr>
                <td><strong>{{ str_replace('-', ' ', ucfirst($s->report_type)) }}</strong></td>
                <td>{{ ucfirst($s->frequency) }}</td>
                <td>{{ $s->email }}</td>
                <td style="color:var(--muted)">{{ $s->last_sent_at?->diffForHumans() ?? 'Never' }}</td>
                <td><span class="badge {{ $s->is_active ? 'badge-green' : 'badge-gray' }}">{{ $s->is_active ? 'Active' : 'Paused' }}</span></td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:var(--muted)">No schedules yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
