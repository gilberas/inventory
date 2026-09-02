@extends('layouts.app')
@section('title', 'Dashboard')

@push('styles')
<style>
    .kpi-section-title {
        font-size:.7rem; font-weight:700; text-transform:uppercase;
        letter-spacing:.08em; color:var(--muted); margin:1.5rem 0 .75rem;
    }
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.875rem; }
    .kpi-card {
        background:var(--surface); border:1px solid var(--border); border-radius:12px;
        padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.35rem;
    }
    .kpi-icon { font-size:.9rem; margin-bottom:.2rem; }
    .kpi-value { font-size:1.55rem; font-weight:800; line-height:1; }
    .kpi-label { font-size:.75rem; color:var(--muted); }
    .kpi-card.danger  { border-color:rgba(239,68,68,.4); }
    .kpi-card.warning { border-color:rgba(245,158,11,.4); }
    .kpi-card.success { border-color:rgba(34,197,94,.4); }
    .kpi-card.sky     { border-color:rgba(56,189,248,.4); }
    .branch-selector { display:flex; align-items:center; gap:.75rem; }
    .branch-selector label { font-size:.8rem; color:var(--muted); white-space:nowrap; }
    .branch-selector select { min-width:200px; padding:.45rem .75rem; font-size:.8rem; }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
    .top5-table th,
    .top5-table td { padding:.7rem 1rem; }
    .sparkline-wrap { height:120px; }
    .notif-badge {
        position:absolute; top:-4px; right:-4px;
        background:var(--danger); color:#fff; border-radius:999px;
        font-size:.65rem; font-weight:700; min-width:16px; height:16px;
        display:flex; align-items:center; justify-content:center; padding:0 3px;
    }
</style>
@endpush

@section('topbar-actions')
    {{-- Branch selector --}}
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

    {{-- Notification bell --}}
    <button class="btn btn-secondary btn-icon" style="position:relative" title="Notifications">
        <i class="fas fa-bell"></i>
        @if($notification_count > 0)
            <span class="notif-badge">{{ $notification_count }}</span>
        @endif
    </button>
@endsection

@section('content')

{{-- ── INVENTORY ─────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-boxes"></i> &nbsp;Inventory</p>
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

{{-- ── SALES ─────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-receipt"></i> &nbsp;Sales (Delivered)</p>
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
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-chart-line"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($sales['salesThisYear'], 2) }}</span>
        <span class="kpi-label">This Year</span>
    </div>
</div>

{{-- ── PURCHASING ────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-shopping-cart"></i> &nbsp;Purchasing</p>
<div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr))">
    <div class="kpi-card">
        <span class="kpi-icon" style="color:var(--primary)"><i class="fas fa-file-invoice-dollar"></i></span>
        <span class="kpi-value">{{ number_format($purchases['purchaseValueThisMonth'], 2) }}</span>
        <span class="kpi-label">Purchase Value This Month</span>
    </div>
    <div class="kpi-card {{ $purchases['pendingPoCount'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-hourglass-half"></i></span>
        <span class="kpi-value" style="{{ $purchases['pendingPoCount'] > 0 ? 'color:var(--warning)' : '' }}">
            {{ number_format($purchases['pendingPoCount']) }}
        </span>
        <span class="kpi-label">Pending Purchase Orders</span>
    </div>
</div>

{{-- ── FINANCIAL ─────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-wallet"></i> &nbsp;Financial</p>
<div class="kpi-grid">
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
    <div class="kpi-card {{ $financial['grossProfitThisMonth'] >= 0 ? 'success' : 'danger' }}">
        <span class="kpi-icon" style="color:{{ $financial['grossProfitThisMonth'] >= 0 ? 'var(--success)' : 'var(--danger)' }}">
            <i class="fas fa-percentage"></i>
        </span>
        <span class="kpi-value" style="color:{{ $financial['grossProfitThisMonth'] >= 0 ? 'var(--success)' : 'var(--danger)' }}">
            {{ number_format($financial['grossProfitThisMonth'], 2) }}
        </span>
        <span class="kpi-label">Gross Profit This Month</span>
    </div>
    <div class="kpi-card {{ $financial['outstandingReceivables'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-user-clock"></i></span>
        <span class="kpi-value">{{ number_format($financial['outstandingReceivables'], 2) }}</span>
        <span class="kpi-label">Outstanding Receivables</span>
    </div>
    <div class="kpi-card {{ $financial['outstandingPayables'] > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-store-slash"></i></span>
        <span class="kpi-value">{{ number_format($financial['outstandingPayables'], 2) }}</span>
        <span class="kpi-label">Outstanding Payables</span>
    </div>
</div>

{{-- ── SPARKLINE ─────────────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-chart-area" style="color:var(--primary)"></i> &nbsp;Sales — Last 30 Days</h3>
    <div class="sparkline-wrap">
        <canvas id="sparklineChart"></canvas>
    </div>
</div>

{{-- ── TOP 5 PRODUCTS ───────────────────────────────── --}}
<div class="chart-card">
    <h3><i class="fas fa-trophy" style="color:var(--warning)"></i> &nbsp;Top 5 Products This Month (by Qty Sold)</h3>
    @if($sales['top5ProductsThisMonth']->isEmpty())
        <div class="empty-state" style="padding:2rem 0">
            <i class="fas fa-box-open"></i>
            <p>No delivered sales this month yet.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table class="top5-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="text-align:right">Qty Sold</th>
                        <th style="text-align:right">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sales['top5ProductsThisMonth'] as $i => $product)
                    <tr>
                        <td>
                            <span class="badge {{ $i === 0 ? 'badge-amber' : 'badge-gray' }}">
                                {{ $i + 1 }}
                            </span>
                        </td>
                        <td style="font-weight:600">{{ $product->name }}</td>
                        <td class="font-mono" style="font-size:.8rem; color:var(--muted)">{{ $product->sku }}</td>
                        <td style="text-align:right; font-weight:700">{{ number_format($product->total_qty, 2) }}</td>
                        <td style="text-align:right; color:var(--success)">{{ number_format($product->total_revenue, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    const raw = @json($sales['salesSparkline']);
    const labels = raw.map(d => d.date.slice(5)); // MM-DD
    const data   = raw.map(d => d.total);

    const ctx = document.getElementById('sparklineChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,.12)',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
                fill: true,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: {
                callbacks: {
                    label: ctx => ' ' + Number(ctx.raw).toLocaleString(undefined, {minimumFractionDigits:2}),
                },
            }},
            scales: {
                x: { grid: { color: 'rgba(51,65,85,.5)' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                y: { grid: { color: 'rgba(51,65,85,.5)' }, ticks: { color: '#94a3b8', font: { size: 11 } } },
            },
        },
    });

    /*
     * Real-time sales updates via Laravel Echo (requires §5.1 + Pusher setup):
     * composer require pusher/pusher-php-server
     * npm install laravel-echo pusher-js
     * BROADCAST_DRIVER=pusher in .env
     *
     * window.Echo.private(`tenant.{{ auth()->user()->tenant_id }}.branch.{{ $selected_warehouse ?? 'all' }}`)
     *     .listen('.sale.completed', (e) => {
     *         // Refresh KPI cards or update inline
     *         console.log('New sale:', e);
     *     });
     */
})();
</script>
@endpush
