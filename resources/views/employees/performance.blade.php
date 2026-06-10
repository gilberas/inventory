@extends('layouts.dashboard')
@section('title', $employee->name . ' — Performance')
@section('breadcrumb', 'Employees / ' . $employee->name . ' / Performance')

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
        <div><label>From</label><input type="date" name="start_date" value="{{ $start }}"></div>
        <div><label>To</label><input type="date" name="end_date" value="{{ $end }}"></div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <a href="{{ route('employees.show', $employee) }}" class="btn btn-secondary btn-sm">Back</a>
    </form>
</div></div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1rem">
    <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:1.75rem;font-weight:700;color:var(--primary)">{{ number_format($salesRevenue, 2) }}</div>
        <div style="color:var(--muted)">Sales Revenue (TZS)</div>
    </div>
    <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:1.75rem;font-weight:700">{{ $salesCount }}</div>
        <div style="color:var(--muted)">Sales Transactions</div>
    </div>
    <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:1.75rem;font-weight:700">{{ $grnCount }}</div>
        <div style="color:var(--muted)">GRNs Received</div>
    </div>
    <div class="card" style="padding:1.25rem;text-align:center">
        <div style="font-size:1.75rem;font-weight:700;color:var(--success)">{{ $presentDays }}</div>
        <div style="color:var(--muted)">Days Present</div>
    </div>
</div>

@if(!$employee->user_id)
<div class="alert alert-warning">This employee is not linked to a user account — sales and GRN data unavailable.</div>
@endif
@endsection
