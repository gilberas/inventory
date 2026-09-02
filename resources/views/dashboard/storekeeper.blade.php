@extends('layouts.app')
@section('title', 'Warehouse Dashboard')

@push('styles')
<style>
    .stock-bar{height:6px;border-radius:3px;background:var(--border);overflow:hidden;margin-top:.35rem}
    .stock-bar-fill{height:100%;border-radius:3px;transition:width .3s}
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
@endphp

<div class="dash-greeting">
    <h1>{{ $greeting }}, {{ $firstName }}!</h1>
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Warehouse overview</p>
</div>

{{-- ── STOCK ALERTS ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-triangle-exclamation"></i> &nbsp;Stock Alerts</p>
<div class="kpi-grid">
    <x-kpi-card title="Low Stock Items"      value="{{ number_format($inventory['lowStockCount']) }}"    icon="exclamation-triangle" color="{{ $inventory['lowStockCount'] > 0 ? 'orange' : 'green' }}" :href="route('reports.low-stock')" />
    <x-kpi-card title="Out of Stock"         value="{{ number_format($inventory['outOfStockCount']) }}"  icon="times-circle"         color="{{ $inventory['outOfStockCount'] > 0 ? 'red' : 'green' }}" />
    <x-kpi-card title="Expiring ≤ 30 Days"   value="{{ number_format($inventory['expiringSoonCount']) }}" icon="calendar-times"       color="{{ $inventory['expiringSoonCount'] > 0 ? 'orange' : 'primary' }}" />
    <x-kpi-card title="GRNs Confirmed Today" value="{{ number_format($grns_confirmed_today) }}"          icon="truck-ramp-box"        color="{{ $grns_confirmed_today > 0 ? 'green' : 'primary' }}" />
</div>

{{-- ── TRANSFERS ───────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-truck-moving"></i> &nbsp;Transfers</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <x-kpi-card title="Awaiting Dispatch"     value="{{ number_format($transfers_to_dispatch) }}" icon="arrow-up-from-bracket"   color="{{ $transfers_to_dispatch > 0 ? 'orange' : 'primary' }}" />
    <x-kpi-card title="In Transit (to receive)" value="{{ number_format($transfers_to_receive) }}" icon="arrow-down-to-bracket" color="{{ $transfers_to_receive > 0 ? 'blue' : 'primary' }}" />
</div>

{{-- ── LOW STOCK TABLE ────────────────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-triangle-exclamation" style="color:var(--warning)"></i> &nbsp;Low Stock Products (Top 10)</h3>
    @if($low_stock_products->isEmpty())
        <div class="empty-state" style="padding:2rem 0">
            <i class="fas fa-check-circle" style="color:var(--success)"></i>
            <p style="color:var(--success)">All products are adequately stocked.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Product</th><th>SKU</th><th style="text-align:right">On Hand</th><th style="text-align:right">Reorder Level</th><th>Stock Level</th></tr>
                </thead>
                <tbody>
                    @foreach($low_stock_products as $p)
                    @php
                        $pct   = $p->reorder_level > 0 ? min(100, round(($p->quantity / $p->reorder_level) * 100)) : 100;
                        $color = $pct <= 25 ? 'var(--danger)' : ($pct <= 60 ? 'var(--warning)' : 'var(--success)');
                    @endphp
                    <tr>
                        <td style="font-weight:600">{{ $p->name }}</td>
                        <td style="color:var(--muted);font-size:.8rem">{{ $p->sku }}</td>
                        <td style="text-align:right;font-weight:700;color:{{ $color }}">{{ number_format($p->quantity, 2) }}</td>
                        <td style="text-align:right;color:var(--muted)">{{ number_format($p->reorder_level, 2) }}</td>
                        <td style="min-width:100px">
                            <div class="stock-bar">
                                <div class="stock-bar-fill" style="width:{{ $pct }}%;background:{{ $color }}"></div>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- ── EXPIRING PRODUCTS ───────────────────────────────────────────────── --}}
@if($expiring_products->isNotEmpty())
<div class="chart-card">
    <h3><i class="fas fa-calendar-times" style="color:var(--warning)"></i> &nbsp;Expiring Products (Next 30 Days)</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Product</th><th>SKU</th><th>Expiry Date</th><th>Days Left</th><th style="text-align:right">Qty</th></tr>
            </thead>
            <tbody>
                @foreach($expiring_products as $p)
                @php
                    $daysLeft = \Carbon\Carbon::parse($p->expiry_date)->diffInDays(today(), false) * -1;
                    $expColor = $daysLeft <= 7 ? 'var(--danger)' : ($daysLeft <= 14 ? 'var(--warning)' : 'var(--info)');
                @endphp
                <tr>
                    <td style="font-weight:600">{{ $p->name }}</td>
                    <td style="color:var(--muted);font-size:.8rem">{{ $p->sku }}</td>
                    <td>{{ \Carbon\Carbon::parse($p->expiry_date)->format('d M Y') }}</td>
                    <td><span style="color:{{ $expColor }};font-weight:600;font-size:.8rem">{{ $daysLeft }}d</span></td>
                    <td style="text-align:right">{{ number_format($p->quantity, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- ── PENDING GRNs ───────────────────────────────────────────────────── --}}
<div class="chart-card">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
        <h3 style="margin:0"><i class="fas fa-clipboard-list" style="color:var(--primary)"></i> &nbsp;Pending GRNs</h3>
        @if(Route::has('grn.index'))
            <a href="{{ route('grn.index') }}" class="btn btn-primary btn-sm">Go to Receiving</a>
        @endif
    </div>
    @if($pending_grns->isEmpty())
        <div class="empty-state" style="padding:1.5rem 0">
            <i class="fas fa-check-double"></i>
            <p>No pending deliveries.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Reference</th><th>PO</th><th>Status</th></tr></thead>
                <tbody>
                    @foreach($pending_grns as $grn)
                    <tr>
                        <td style="font-weight:600">{{ $grn->reference_no }}</td>
                        <td style="color:var(--muted);font-size:.8rem">#{{ $grn->purchase_order_id }}</td>
                        <td><span class="badge badge-amber">{{ ucfirst($grn->status) }}</span></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
