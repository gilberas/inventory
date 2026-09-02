@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    .branch-selector { display:flex; align-items:center; gap:.75rem; }
    .branch-selector label { font-size:.8rem; color:var(--muted); white-space:nowrap; }
    .branch-selector select { min-width:200px; padding:.45rem .75rem; font-size:.8rem; }
    .sparkline-wrap { height:120px; }
</style>
@endpush

@section('topbar-actions')
    @if($can_select_branch && $warehouses->count() > 1)
        <form method="GET" action="{{ route('dashboard') }}" class="branch-selector">
            <label><i class="fas fa-code-branch"></i> Branch</label>
            <select name="branch_id" onchange="this.form.submit()">
                <option value="">All branches (consolidated)</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $selected_warehouse == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }} ({{ $wh->code }})
                    </option>
                @endforeach
            </select>
        </form>
    @endif
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
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Business overview</p>
</div>

{{-- ── INVENTORY ──────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-boxes"></i> &nbsp;Inventory</p>
<div class="kpi-grid">
    <x-kpi-card title="Active Products"     value="{{ number_format($inventory['totalProducts']) }}"      icon="cubes"                color="primary" />
    <x-kpi-card title="Low Stock"           value="{{ number_format($inventory['lowStockCount']) }}"       icon="exclamation-triangle"  color="orange"
                :href="route('reports.low-stock')" />
    <x-kpi-card title="Out of Stock"        value="{{ number_format($inventory['outOfStockCount']) }}"     icon="times-circle"          color="red" />
    <x-kpi-card title="Expiring ≤ 30 days"  value="{{ number_format($inventory['expiringSoonCount']) }}"   icon="calendar-times"        color="orange" />
</div>

{{-- ── SALES ──────────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;Sales</p>
<div class="kpi-grid">
    <x-kpi-card title="Today (TZS)"       value="{{ number_format($sales['salesToday'], 2) }}"       icon="sun"          color="blue" />
    <x-kpi-card title="This Week (TZS)"   value="{{ number_format($sales['salesThisWeek'], 2) }}"    icon="calendar-week" color="blue" />
    <x-kpi-card title="This Month (TZS)"  value="{{ number_format($sales['salesThisMonth'], 2) }}"   icon="calendar-alt" color="green" />
    <x-kpi-card title="This Year (TZS)"   value="{{ number_format($sales['salesThisYear'], 2) }}"    icon="chart-line"   color="green" />
</div>

{{-- ── PURCHASING ─────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-shopping-cart"></i> &nbsp;Purchasing</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <x-kpi-card title="Purchase Value This Month" value="{{ number_format($purchases['purchaseValueThisMonth'], 2) }}" icon="file-invoice-dollar" color="primary" />
    <x-kpi-card title="Pending Purchase Orders"   value="{{ number_format($purchases['pendingPoCount']) }}"            icon="hourglass-half"      color="{{ $purchases['pendingPoCount'] > 0 ? 'orange' : 'primary' }}" />
</div>

{{-- ── FINANCIAL ──────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-wallet"></i> &nbsp;Financial</p>
<div class="kpi-grid">
    <x-kpi-card title="Revenue This Month"        value="{{ number_format($financial['revenueThisMonth'], 2) }}"     icon="hand-holding-usd" color="green" />
    <x-kpi-card title="Expenses This Month"       value="{{ number_format($financial['expensesThisMonth'], 2) }}"   icon="money-bill-wave"  color="red" />
    <x-kpi-card title="Gross Profit This Month"   value="{{ number_format($financial['grossProfitThisMonth'], 2) }}" icon="percentage"       color="{{ $financial['grossProfitThisMonth'] >= 0 ? 'green' : 'red' }}" />
    <x-kpi-card title="Outstanding Receivables"   value="{{ number_format($financial['outstandingReceivables'], 2) }}" icon="user-clock"     color="{{ $financial['outstandingReceivables'] > 0 ? 'orange' : 'primary' }}" />
    <x-kpi-card title="Outstanding Payables"      value="{{ number_format($financial['outstandingPayables'], 2) }}"  icon="store-slash"      color="{{ $financial['outstandingPayables'] > 0 ? 'orange' : 'primary' }}" />
</div>

{{-- ── SPARKLINE ──────────────────────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-chart-area" style="color:var(--primary)"></i> &nbsp;Sales — Last 30 Days</h3>
    <div class="sparkline-wrap">
        <canvas id="sparklineChart"></canvas>
    </div>
</div>

{{-- ── TOP 5 PRODUCTS ─────────────────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-trophy" style="color:var(--warning)"></i> &nbsp;Top 5 Products This Month</h3>
    @if($sales['top5ProductsThisMonth']->isEmpty())
        <div class="empty-state" style="padding:2rem 0">
            <i class="fas fa-box-open"></i>
            <p>No sales this month yet.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>#</th><th>Product</th><th>SKU</th><th style="text-align:right">Qty</th><th style="text-align:right">Revenue</th></tr>
                </thead>
                <tbody>
                    @foreach($sales['top5ProductsThisMonth'] as $i => $p)
                    <tr>
                        <td><span class="badge {{ $i === 0 ? 'badge-amber' : 'badge-gray' }}">{{ $i + 1 }}</span></td>
                        <td style="font-weight:600">{{ $p->name }}</td>
                        <td style="color:var(--muted);font-size:.8rem">{{ $p->sku }}</td>
                        <td style="text-align:right;font-weight:700">{{ number_format($p->total_qty, 2) }}</td>
                        <td style="text-align:right;color:var(--success)">{{ number_format($p->total_revenue, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@if($branches_summary)
{{-- ── BRANCHES SUMMARY ───────────────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-code-branch" style="color:var(--primary)"></i> &nbsp;Branch Performance — Today</h3>
    @if(empty($branches_summary))
        <div class="empty-state" style="padding:1.5rem 0"><p>No branch data yet.</p></div>
    @else
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Branch</th><th style="text-align:right">Sales Today</th><th style="text-align:right">Low Stock</th></tr></thead>
                <tbody>
                    @foreach($branches_summary as $b)
                    <tr>
                        <td style="font-weight:600">{{ $b['branch_name'] }}</td>
                        <td style="text-align:right;color:var(--success)">{{ number_format($b['today_sales'], 2) }}</td>
                        <td style="text-align:right">
                            @if($b['low_stock_count'] > 0)
                                <span class="badge badge-amber">{{ $b['low_stock_count'] }}</span>
                            @else
                                <span class="badge badge-green">OK</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endif

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const raw    = @json($sales['salesSparkline']);
    const labels = raw.map(d => d.date.slice(5));
    const data   = raw.map(d => d.total);
    const ctx    = document.getElementById('sparklineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: { labels, datasets: [{ data, borderColor:'#6366f1', backgroundColor:'rgba(99,102,241,.12)', borderWidth:2, pointRadius:0, tension:.4, fill:true }] },
        options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales: { x:{ grid:{ color:'rgba(51,65,85,.5)' }, ticks:{ color:'#94a3b8', font:{ size:11 } } }, y:{ grid:{ color:'rgba(51,65,85,.5)' }, ticks:{ color:'#94a3b8', font:{ size:11 } } } } },
    });
})();
</script>
@endpush
