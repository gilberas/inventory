@extends('layouts.app')
@section('title', 'My Dashboard')

@push('styles')
<style>
    .pos-btn{display:flex;align-items:center;justify-content:center;gap:.75rem;width:100%;
             padding:1.25rem 2rem;font-size:1.1rem;font-weight:800;background:var(--success);
             color:#fff;border:none;border-radius:12px;cursor:pointer;text-decoration:none;
             margin-bottom:1.5rem;transition:opacity .15s}
    .pos-btn:hover{opacity:.88}
    .session-card{background:var(--surface);border:1px solid var(--border);border-radius:12px;
                  padding:1rem 1.25rem;margin-bottom:1rem;display:flex;align-items:center;gap:1rem}
    .session-indicator{width:10px;height:10px;border-radius:50%;flex-shrink:0}
    .session-indicator.active{background:var(--success);box-shadow:0 0 0 3px rgba(34,197,94,.25)}
    .session-indicator.inactive{background:var(--muted)}
</style>
@endpush

@section('topbar-actions')
    <button class="btn btn-secondary btn-icon" style="position:relative" title="Notifications">
        <i class="fas fa-bell"></i>
        @if($notification_count > 0)
            <span class="notif-badge">{{ $notification_count }}</span>
        @endif
    </button>
@endsection

@section('content')

@php
$hour     = (int) now()->format('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', auth()->user()->name)[0];
$avgTransaction = $my_transactions_today > 0
    ? round($my_sales_today / $my_transactions_today, 2) : 0;
@endphp

<div class="dash-greeting">
    <h1>{{ $greeting }}, {{ $firstName }}!</h1>
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Your shift overview</p>
</div>

{{-- ── POS LAUNCH ──────────────────────────────────────────────────────── --}}
@if(Route::has('pos.terminal'))
<a href="{{ route('pos.terminal') }}" class="pos-btn">
    <i class="fas fa-cash-register" style="font-size:1.3rem"></i>
    {{ $open_session ? 'Continue POS Session' : 'Start Shift &amp; Open POS' }}
</a>
@endif

{{-- ── SESSION STATUS ─────────────────────────────────────────────────── --}}
<div class="session-card">
    <div class="session-indicator {{ $open_session ? 'active' : 'inactive' }}"></div>
    <div>
        @if($open_session)
            <div style="font-weight:600;font-size:.875rem">Shift Active</div>
            <div style="color:var(--muted);font-size:.75rem">
                Started {{ \Carbon\Carbon::parse($open_session->opened_at)->format('H:i') }}
                ({{ \Carbon\Carbon::parse($open_session->opened_at)->diffForHumans() }})
            </div>
        @else
            <div style="font-weight:600;font-size:.875rem;color:var(--muted)">No Active Shift</div>
            <div style="color:var(--muted);font-size:.75rem">Open a POS session to start selling</div>
        @endif
    </div>
</div>

{{-- ── MY PERFORMANCE TODAY ───────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-chart-simple"></i> &nbsp;My Performance Today</p>
<div class="kpi-grid">
    <x-kpi-card title="Sales Today (TZS)"  value="{{ number_format($my_sales_today, 2) }}"      icon="sack-dollar" color="green" />
    <x-kpi-card title="Transactions"       value="{{ number_format($my_transactions_today) }}"   icon="receipt"     color="blue" />
    <x-kpi-card title="Avg Transaction"    value="{{ number_format($avgTransaction, 2) }}"        icon="calculator"  color="primary" />
</div>

{{-- ── RECENT SALES ───────────────────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-clock-rotate-left" style="color:var(--primary)"></i> &nbsp;My Recent Sales</h3>
    @if($my_recent_sales->isEmpty())
        <div class="empty-state" style="padding:2rem 0">
            <i class="fas fa-cart-shopping"></i>
            <p>No sales yet today.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Time</th><th>Amount</th><th>Payment</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @foreach($my_recent_sales as $sale)
                    <tr>
                        <td style="color:var(--muted);font-size:.8rem">{{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }}</td>
                        <td style="font-weight:700;color:var(--success)">{{ number_format($sale->grand_total, 2) }}</td>
                        <td><span class="badge badge-sky">{{ ucfirst($sale->payment_method) }}</span></td>
                        <td>
                            <span class="badge {{ $sale->status === 'completed' ? 'badge-green' : 'badge-amber' }}">
                                {{ ucfirst($sale->status) }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
