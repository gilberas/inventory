@extends('layouts.app')
@section('title', 'Branch Dashboard')

@push('styles')
<style>
    .kpi-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:1.5rem 0 .75rem; }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.875rem; }
    .kpi-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.35rem; }
    .kpi-icon { font-size:.9rem; margin-bottom:.2rem; }
    .kpi-value { font-size:1.55rem; font-weight:800; line-height:1; }
    .kpi-label { font-size:.75rem; color:var(--muted); }
    .kpi-card.danger  { border-color:rgba(239,68,68,.4); }
    .kpi-card.warning { border-color:rgba(245,158,11,.4); }
    .kpi-card.success { border-color:rgba(34,197,94,.4); }
    .kpi-card.sky     { border-color:rgba(56,189,248,.4); }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
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

{{-- ── SALES (branch-scoped) ──────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;Sales — This Branch</p>
<div class="kpi-grid">
    <div class="kpi-card sky">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-sun"></i></span>
        <span class="kpi-value" style="color:var(--info)">{{ number_format($sales['salesToday'], 2) }}</span>
        <span class="kpi-label">Today</span>
    </div>
    <div class="kpi-card sky">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-calendar-week"></i></span>
        <span class="kpi-value" style="color:var(--info)">{{ number_format($sales['salesThisWeek'], 2) }}</span>
        <span class="kpi-label">This Week</span>
    </div>
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-calendar-alt"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($sales['salesThisMonth'], 2) }}</span>
        <span class="kpi-label">This Month</span>
    </div>
</div>

{{-- ── INVENTORY (branch-scoped) ──────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-boxes"></i> &nbsp;Inventory — This Branch</p>
<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-icon" style="color:var(--primary)"><i class="fas fa-cubes"></i></span>
        <span class="kpi-value">{{ number_format($inventory['totalProducts']) }}</span>
        <span class="kpi-label">Active Products</span>
    </div>
    <div class="kpi-card warning">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-exclamation-triangle"></i></span>
        <span class="kpi-value" style="color:var(--warning)">{{ number_format($inventory['lowStockCount']) }}</span>
        <span class="kpi-label">Low Stock</span>
    </div>
    <div class="kpi-card danger">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-times-circle"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($inventory['outOfStockCount']) }}</span>
        <span class="kpi-label">Out of Stock</span>
    </div>
    <div class="kpi-card warning">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-calendar-times"></i></span>
        <span class="kpi-value" style="color:var(--warning)">{{ number_format($inventory['expiringSoonCount']) }}</span>
        <span class="kpi-label">Expiring ≤ 30 days</span>
    </div>
</div>

{{-- ── PENDING ACTIONS ────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-clock"></i> &nbsp;Pending Actions</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <div class="kpi-card {{ $pending_requisitions > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-clipboard-list"></i></span>
        <span class="kpi-value">{{ number_format($pending_requisitions) }}</span>
        <span class="kpi-label">Pending Requisitions</span>
    </div>
    <div class="kpi-card {{ $purchases['pendingPoCount'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-hourglass-half"></i></span>
        <span class="kpi-value">{{ number_format($purchases['pendingPoCount']) }}</span>
        <span class="kpi-label">Pending POs</span>
    </div>
</div>

{{-- ── FINANCIAL SUMMARY (limited: no full P&L) ──────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-wallet"></i> &nbsp;Financial Summary</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-hand-holding-usd"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($financial['revenueThisMonth'], 2) }}</span>
        <span class="kpi-label">Revenue This Month</span>
    </div>
    <div class="kpi-card danger">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-money-bill-wave"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($financial['expensesThisMonth'], 2) }}</span>
        <span class="kpi-label">Expenses This Month</span>
    </div>
</div>

@endsection
