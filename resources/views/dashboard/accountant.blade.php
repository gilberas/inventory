@extends('layouts.app')
@section('title', 'Financial Dashboard')

@push('styles')
<style>
    .kpi-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:1.5rem 0 .75rem; }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.875rem; }
    .kpi-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.35rem; }
    .kpi-icon { font-size:.9rem; margin-bottom:.2rem; }
    .kpi-value { font-size:1.55rem; font-weight:800; line-height:1; }
    .kpi-label { font-size:.75rem; color:var(--muted); }
    .kpi-sub { font-size:.7rem; color:var(--muted); margin-top:.1rem; }
    .kpi-card.danger  { border-color:rgba(239,68,68,.4); }
    .kpi-card.warning { border-color:rgba(245,158,11,.4); }
    .kpi-card.success { border-color:rgba(34,197,94,.4); }
    .kpi-card.sky     { border-color:rgba(56,189,248,.4); }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
    .vat-row { display:flex; align-items:center; justify-content:space-between; padding:.65rem 0; border-bottom:1px solid var(--border); font-size:.875rem; }
    .vat-row:last-child { border-bottom:none; }
    .vat-row.total { font-weight:700; padding-top:.85rem; }
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

{{-- ── REVENUE & PROFIT ───────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-chart-line"></i> &nbsp;Revenue & Profit — This Month</p>
<div class="kpi-grid">
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-hand-holding-usd"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($financial['revenueThisMonth'], 2) }}</span>
        <span class="kpi-label">Revenue</span>
        @if($revenue_last_month > 0)
            @php $revDiff = $financial['revenueThisMonth'] - $revenue_last_month; @endphp
            <span class="kpi-sub" style="color:{{ $revDiff >= 0 ? 'var(--success)' : 'var(--danger)' }}">
                {{ $revDiff >= 0 ? '▲' : '▼' }} {{ number_format(abs($revDiff / $revenue_last_month * 100), 1) }}% vs last month
            </span>
        @endif
    </div>
    <div class="kpi-card danger">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-boxes-stacked"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($cogs_this_month, 2) }}</span>
        <span class="kpi-label">Cost of Goods Sold</span>
    </div>
    <div class="kpi-card {{ ($financial['revenueThisMonth'] - $cogs_this_month) >= 0 ? 'success' : 'danger' }}">
        @php $grossProfit = $financial['revenueThisMonth'] - $cogs_this_month; @endphp
        <span class="kpi-icon" style="color:{{ $grossProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}"><i class="fas fa-percentage"></i></span>
        <span class="kpi-value" style="color:{{ $grossProfit >= 0 ? 'var(--success)' : 'var(--danger)' }}">{{ number_format($grossProfit, 2) }}</span>
        <span class="kpi-label">Gross Profit</span>
    </div>
    <div class="kpi-card danger">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-money-bill-wave"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($financial['expensesThisMonth'], 2) }}</span>
        <span class="kpi-label">Expenses</span>
    </div>
</div>

{{-- ── PAYABLES & RECEIVABLES ─────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-balance-scale"></i> &nbsp;Payables & Receivables</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <div class="kpi-card {{ $financial['outstandingReceivables'] > 0 ? 'sky' : '' }}">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-user-clock"></i></span>
        <span class="kpi-value">{{ number_format($financial['outstandingReceivables'], 2) }}</span>
        <span class="kpi-label">Outstanding Receivables</span>
    </div>
    <div class="kpi-card {{ $financial['outstandingPayables'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-store-slash"></i></span>
        <span class="kpi-value">{{ number_format($financial['outstandingPayables'], 2) }}</span>
        <span class="kpi-label">Outstanding Payables</span>
    </div>
    @if($pending_expense_approvals > 0)
    <div class="kpi-card warning">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-file-circle-exclamation"></i></span>
        <span class="kpi-value" style="color:var(--warning)">{{ number_format($pending_expense_approvals) }}</span>
        <span class="kpi-label">Expenses Pending Approval</span>
    </div>
    @endif
    @if($overdue_invoices > 0)
    <div class="kpi-card danger">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-file-circle-xmark"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($overdue_invoices) }}</span>
        <span class="kpi-label">Overdue Supplier Invoices</span>
    </div>
    @endif
</div>

{{-- ── VAT SUMMARY ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;VAT Summary — This Month</p>
<div class="chart-card">
    @php $netVat = $vat_collected - $vat_paid; @endphp
    <div class="vat-row">
        <span><i class="fas fa-arrow-right" style="color:var(--success)"></i> &nbsp;VAT Collected on Sales</span>
        <span style="color:var(--success);font-weight:600">TZS {{ number_format($vat_collected, 2) }}</span>
    </div>
    <div class="vat-row">
        <span><i class="fas fa-arrow-left" style="color:var(--warning)"></i> &nbsp;VAT Paid on Purchases</span>
        <span style="color:var(--warning);font-weight:600">TZS {{ number_format($vat_paid, 2) }}</span>
    </div>
    <div class="vat-row total">
        <span><i class="fas fa-equals" style="color:var(--primary)"></i> &nbsp;Net VAT Payable to TRA</span>
        <span style="color:{{ $netVat >= 0 ? 'var(--danger)' : 'var(--success)' }};font-size:1.1rem">
            TZS {{ number_format($netVat, 2) }}
        </span>
    </div>
    @if(Route::has('reports.financial.vat'))
    <div style="margin-top:1rem;text-align:right">
        <a href="{{ route('reports.financial.vat') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-file-export"></i> Export VAT Report
        </a>
    </div>
    @endif
</div>

{{-- ── QUICK LINKS ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-link"></i> &nbsp;Quick Links</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))">
    @if(Route::has('reports.index'))
    <a href="{{ route('reports.index') }}" class="kpi-card" style="text-decoration:none;cursor:pointer">
        <span class="kpi-icon" style="color:var(--primary)"><i class="fas fa-chart-bar"></i></span>
        <span class="kpi-value" style="font-size:1rem">P&L Report</span>
    </a>
    @endif
    @if(Route::has('reports.financial.vat'))
    <a href="{{ route('reports.financial.vat') }}" class="kpi-card" style="text-decoration:none;cursor:pointer">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-receipt"></i></span>
        <span class="kpi-value" style="font-size:1rem">VAT Report</span>
    </a>
    @endif
    @if(Route::has('expenses.index'))
    <a href="{{ route('expenses.index') }}" class="kpi-card" style="text-decoration:none;cursor:pointer">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-money-bill-wave"></i></span>
        <span class="kpi-value" style="font-size:1rem">Expenses</span>
    </a>
    @endif
</div>

@endsection
