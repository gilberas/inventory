@extends('layouts.app')
@section('title', $customer->name)
@section('breadcrumb', 'Sales / Customers / ' . $customer->name)
@section('topbar-actions')
    <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
    <a href="{{ route('customers.index') }}" class="btn btn-secondary btn-sm">← Back</a>
@endsection
@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem">

    {{-- Profile card --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Profile</h3></div>
        <table class="table" style="margin:0">
            <tr><td style="color:var(--muted);width:40%">Name</td><td>{{ $customer->name }}</td></tr>
            <tr><td style="color:var(--muted)">Phone</td><td>{{ $customer->phone ?? '—' }}</td></tr>
            <tr><td style="color:var(--muted)">Email</td><td>{{ $customer->email ?? '—' }}</td></tr>
            <tr><td style="color:var(--muted)">Address</td><td>{{ $customer->address ?? '—' }}</td></tr>
            <tr><td style="color:var(--muted)">Type</td>
                <td><span class="badge {{ $customer->type === 'wholesale' ? 'badge-sky' : 'badge-green' }}">
                    {{ ucfirst($customer->type ?? 'retail') }}</span></td>
            </tr>
            <tr><td style="color:var(--muted)">Status</td>
                <td><span class="badge {{ $customer->is_active ? 'badge-green' : 'badge-red' }}">
                    {{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        </table>
    </div>

    {{-- Account summary --}}
    <div class="card">
        <div class="card-header"><h3 class="card-title">Account</h3></div>
        <table class="table" style="margin:0">
            <tr><td style="color:var(--muted);width:50%">Total Orders</td>
                <td><strong>{{ number_format($stats['total_purchases']) }}</strong></td></tr>
            <tr><td style="color:var(--muted)">Total Spent</td>
                <td><strong>{{ number_format($stats['total_spent'], 2) }}</strong></td></tr>
            <tr><td style="color:var(--muted)">Outstanding Balance</td>
                <td>
                    @php $bal = $stats['balance']; @endphp
                    <span class="badge {{ $bal > 0 ? 'badge-sky' : 'badge-green' }}">
                        {{ number_format($bal, 2) }}
                    </span>
                </td>
            </tr>
            <tr><td style="color:var(--muted)">Credit Limit</td>
                <td>{{ number_format($customer->credit_limit ?? 0, 2) }}</td></tr>
            <tr><td style="color:var(--muted)">Loyalty Points</td>
                <td><strong>{{ number_format($stats['loyalty_points']) }}</strong></td></tr>
        </table>
    </div>

</div>

@endsection
