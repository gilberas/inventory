@extends('layouts.app')
@section('title', 'Branch Dashboard')

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
@endphp

<div class="dash-greeting">
    <h1>{{ $greeting }}, {{ $firstName }}!</h1>
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Branch overview</p>
</div>

{{-- ── SALES ──────────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;Sales — This Branch</p>
<div class="kpi-grid">
    <x-kpi-card title="Today (TZS)"      value="{{ number_format($sales['salesToday'], 2) }}"      icon="sun"           color="blue" />
    <x-kpi-card title="This Week (TZS)"  value="{{ number_format($sales['salesThisWeek'], 2) }}"   icon="calendar-week" color="blue" />
    <x-kpi-card title="This Month (TZS)" value="{{ number_format($sales['salesThisMonth'], 2) }}"  icon="calendar-alt"  color="green" />
</div>

{{-- ── INVENTORY ──────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-boxes"></i> &nbsp;Inventory — This Branch</p>
<div class="kpi-grid">
    <x-kpi-card title="Active Products"    value="{{ number_format($inventory['totalProducts']) }}"      icon="cubes"                color="primary" />
    <x-kpi-card title="Low Stock"          value="{{ number_format($inventory['lowStockCount']) }}"       icon="exclamation-triangle"  color="orange" />
    <x-kpi-card title="Out of Stock"       value="{{ number_format($inventory['outOfStockCount']) }}"     icon="times-circle"          color="red" />
    <x-kpi-card title="Expiring ≤ 30 days" value="{{ number_format($inventory['expiringSoonCount']) }}"   icon="calendar-times"        color="orange" />
</div>

{{-- ── PENDING ACTIONS ────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-clock"></i> &nbsp;Pending Actions</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <x-kpi-card title="Pending Requisitions" value="{{ number_format($pending_requisitions) }}" icon="clipboard-list" color="{{ $pending_requisitions > 0 ? 'orange' : 'primary' }}" :href="route('requisitions.index')" />
    <x-kpi-card title="Pending POs"          value="{{ number_format($purchases['pendingPoCount']) }}"  icon="hourglass-half" color="{{ $purchases['pendingPoCount'] > 0 ? 'orange' : 'primary' }}" :href="route('purchases.index')" />
</div>

{{-- ── FINANCIAL SUMMARY ──────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-wallet"></i> &nbsp;Financial Summary</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <x-kpi-card title="Revenue This Month"  value="{{ number_format($financial['revenueThisMonth'], 2) }}"   icon="hand-holding-usd" color="green" />
    <x-kpi-card title="Expenses This Month" value="{{ number_format($financial['expensesThisMonth'], 2) }}"  icon="money-bill-wave"  color="red" />
</div>

@endsection
