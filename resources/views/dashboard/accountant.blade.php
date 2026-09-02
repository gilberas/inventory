@extends('layouts.app')
@section('title', 'Financial Dashboard')

@push('styles')
<style>
    .vat-row{display:flex;align-items:center;justify-content:space-between;padding:.65rem 0;border-bottom:1px solid var(--border);font-size:.875rem}
    .vat-row:last-child{border-bottom:none}
    .vat-row.total{font-weight:700;padding-top:.85rem}
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
$grossProfit = $financial['revenueThisMonth'] - $cogs_this_month;
$netVat      = $vat_collected - $vat_paid;
@endphp

<div class="dash-greeting">
    <h1>{{ $greeting }}, {{ $firstName }}!</h1>
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Financial overview</p>
</div>

{{-- ── REVENUE & PROFIT ────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-chart-line"></i> &nbsp;Revenue &amp; Profit — This Month</p>
<div class="kpi-grid">
    <x-kpi-card
        title="Revenue"
        value="{{ number_format($financial['revenueThisMonth'], 2) }}"
        icon="hand-holding-usd"
        color="green"
        :sub="$revenue_last_month > 0 ? (($financial['revenueThisMonth'] >= $revenue_last_month ? '▲ ' : '▼ ') . number_format(abs(($financial['revenueThisMonth'] - $revenue_last_month) / $revenue_last_month * 100), 1) . '% vs last month') : null"
    />
    <x-kpi-card title="Cost of Goods Sold" value="{{ number_format($cogs_this_month, 2) }}"   icon="boxes-stacked"   color="red" />
    <x-kpi-card title="Gross Profit"        value="{{ number_format($grossProfit, 2) }}"        icon="percentage"      color="{{ $grossProfit >= 0 ? 'green' : 'red' }}" />
    <x-kpi-card title="Expenses"            value="{{ number_format($financial['expensesThisMonth'], 2) }}" icon="money-bill-wave" color="red" />
</div>

{{-- ── PAYABLES & RECEIVABLES ─────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-balance-scale"></i> &nbsp;Payables &amp; Receivables</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <x-kpi-card title="Outstanding Receivables" value="{{ number_format($financial['outstandingReceivables'], 2) }}" icon="user-clock"                color="{{ $financial['outstandingReceivables'] > 0 ? 'blue' : 'primary' }}" />
    <x-kpi-card title="Outstanding Payables"    value="{{ number_format($financial['outstandingPayables'], 2) }}"   icon="store-slash"               color="{{ $financial['outstandingPayables'] > 0 ? 'orange' : 'primary' }}" />
    @if($pending_expense_approvals > 0)
    <x-kpi-card title="Expenses Pending Approval" value="{{ number_format($pending_expense_approvals) }}" icon="file-circle-exclamation" color="orange" :href="route('expenses.index')" />
    @endif
    @if($overdue_invoices > 0)
    <x-kpi-card title="Overdue Supplier Invoices" value="{{ number_format($overdue_invoices) }}"           icon="file-circle-xmark"       color="red" />
    @endif
</div>

{{-- ── VAT SUMMARY ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;VAT Summary — This Month</p>
<div class="chart-card">
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
    <x-kpi-card title="P&amp;L Report"  value=""                    icon="chart-bar"    color="primary" :href="route('reports.index')" />
    @endif
    @if(Route::has('reports.financial.vat'))
    <x-kpi-card title="VAT Report"      value=""                    icon="receipt"      color="orange"  :href="route('reports.financial.vat')" />
    @endif
    @if(Route::has('expenses.index'))
    <x-kpi-card title="Expenses"        value=""                    icon="money-bill-wave" color="red" :href="route('expenses.index')" />
    @endif
</div>

@endsection
