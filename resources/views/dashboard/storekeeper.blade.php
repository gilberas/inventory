@extends('layouts.app')
@section('title', 'Warehouse Dashboard')

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
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
    .stock-bar { height:6px; border-radius:3px; background:var(--border); overflow:hidden; margin-top:.35rem; }
    .stock-bar-fill { height:100%; border-radius:3px; transition:width .3s; }
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

{{-- ── STOCK ALERT OVERVIEW ───────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-triangle-exclamation"></i> &nbsp;Stock Alerts</p>
<div class="kpi-grid">
    <div class="kpi-card {{ $inventory['lowStockCount'] > 0 ? 'warning' : 'success' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-exclamation-triangle"></i></span>
        <span class="kpi-value" style="color:var(--warning)">{{ number_format($inventory['lowStockCount']) }}</span>
        <span class="kpi-label">Low Stock Items</span>
    </div>
    <div class="kpi-card {{ $inventory['outOfStockCount'] > 0 ? 'danger' : 'success' }}">
        <span class="kpi-icon" style="color:var(--danger)"><i class="fas fa-times-circle"></i></span>
        <span class="kpi-value" style="color:var(--danger)">{{ number_format($inventory['outOfStockCount']) }}</span>
        <span class="kpi-label">Out of Stock</span>
    </div>
    <div class="kpi-card {{ $inventory['expiringSoonCount'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-calendar-times"></i></span>
        <span class="kpi-value">{{ number_format($inventory['expiringSoonCount']) }}</span>
        <span class="kpi-label">Expiring ≤ 30 Days</span>
    </div>
    <div class="kpi-card {{ $grns_confirmed_today > 0 ? 'success' : '' }}">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-truck-ramp-box"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($grns_confirmed_today) }}</span>
        <span class="kpi-label">GRNs Confirmed Today</span>
    </div>
</div>

{{-- ── TRANSFERS ───────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-truck-moving"></i> &nbsp;Transfers</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <div class="kpi-card {{ $transfers_to_dispatch > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-arrow-up-from-bracket"></i></span>
        <span class="kpi-value">{{ number_format($transfers_to_dispatch) }}</span>
        <span class="kpi-label">Awaiting Dispatch</span>
    </div>
    <div class="kpi-card {{ $transfers_to_receive > 0 ? 'sky' : '' }}">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-arrow-down-to-bracket"></i></span>
        <span class="kpi-value">{{ number_format($transfers_to_receive) }}</span>
        <span class="kpi-label">In Transit (to receive)</span>
    </div>
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
                        $pct = $p->reorder_level > 0 ? min(100, round(($p->quantity / $p->reorder_level) * 100)) : 100;
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

{{-- ── EXPIRING PRODUCTS TABLE ────────────────────────────────────────── --}}
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
                    <td><span class="badge" style="color:{{ $expColor }};background:transparent;padding:0">{{ $daysLeft }}d</span></td>
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
