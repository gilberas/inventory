@extends('layouts.app')
@section('title', 'My Dashboard')

@push('styles')
<style>
    .kpi-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:1.5rem 0 .75rem; }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.875rem; }
    .kpi-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.35rem; }
    .kpi-icon { font-size:.9rem; margin-bottom:.2rem; }
    .kpi-value { font-size:1.55rem; font-weight:800; line-height:1; }
    .kpi-label { font-size:.75rem; color:var(--muted); }
    .kpi-card.success { border-color:rgba(34,197,94,.4); }
    .kpi-card.sky     { border-color:rgba(56,189,248,.4); }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
    .pos-btn { display:flex; align-items:center; justify-content:center; gap:.75rem; width:100%; padding:1.25rem 2rem; font-size:1.1rem; font-weight:800; background:var(--success); color:#fff; border:none; border-radius:12px; cursor:pointer; text-decoration:none; margin-bottom:1.5rem; transition:opacity .15s; }
    .pos-btn:hover { opacity:.88; }
    .session-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1rem 1.25rem; margin-bottom:1rem; display:flex; align-items:center; gap:1rem; }
    .session-indicator { width:10px; height:10px; border-radius:50%; flex-shrink:0; }
    .session-indicator.active { background:var(--success); box-shadow:0 0 0 3px rgba(34,197,94,.25); }
    .session-indicator.inactive { background:var(--muted); }
    .notif-badge { position:absolute; top:-4px; right:-4px; background:var(--danger); color:#fff; border-radius:999px; font-size:.65rem; font-weight:700; min-width:16px; height:16px; display:flex; align-items:center; justify-content:center; padding:0 3px; }
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

{{-- ── POS LAUNCH BUTTON ──────────────────────────────────────────────── --}}
@if(Route::has('pos.index'))
<a href="{{ route('pos.index') }}" class="pos-btn">
    <i class="fas fa-cash-register" style="font-size:1.3rem"></i>
    {{ $open_session ? 'Continue POS Session' : 'Start Shift & Open POS' }}
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
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-sack-dollar"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($my_sales_today, 2) }}</span>
        <span class="kpi-label">Sales Today (TZS)</span>
    </div>
    <div class="kpi-card sky">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-receipt"></i></span>
        <span class="kpi-value" style="color:var(--info)">{{ number_format($my_transactions_today) }}</span>
        <span class="kpi-label">Transactions Today</span>
    </div>
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
                    <tr>
                        <th>Time</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($my_recent_sales as $sale)
                    <tr>
                        <td style="color:var(--muted);font-size:.8rem">{{ \Carbon\Carbon::parse($sale->created_at)->format('H:i') }}</td>
                        <td style="font-weight:700;color:var(--success)">{{ number_format($sale->grand_total, 2) }}</td>
                        <td>
                            <span class="badge badge-sky">{{ ucfirst($sale->payment_method) }}</span>
                        </td>
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
