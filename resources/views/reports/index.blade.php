@extends('layouts.dashboard')

@section('title', 'Reports')
@section('breadcrumb', 'Overview of all reports')

@section('topbar-actions')
    <span style="font-size:.8rem;color:var(--muted);">{{ now()->format('D, d M Y') }}</span>
@endsection

@section('content')

{{-- Summary stat cards --}}
<div class="stats-grid" style="margin-bottom:2rem;">

    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-coins"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary['total_stock_value'], 2) }}</div>
            <div class="stat-label">Total Stock Value</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber"><i class="fas fa-triangle-exclamation"></i></div>
        <div>
            <div class="stat-value" style="color:var(--warning);">{{ $summary['low_stock_count'] }}</div>
            <div class="stat-label">Low Stock Products</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-calendar-xmark"></i></div>
        <div>
            <div class="stat-value" style="color:var(--danger);">{{ $summary['expiring_soon'] }}</div>
            <div class="stat-label">Expiring in 30 Days</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon sky"><i class="fas fa-right-left"></i></div>
        <div>
            <div class="stat-value" style="color:var(--info);">{{ $summary['transactions_today'] }}</div>
            <div class="stat-label">Transactions Today</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-file-invoice"></i></div>
        <div>
            <div class="stat-value" style="color:var(--success);">${{ number_format($summary['pending_po_value'], 2) }}</div>
            <div class="stat-label">Pending PO Value</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-cart-shopping"></i></div>
        <div>
            <div class="stat-value">${{ number_format($summary['pending_sales_value'], 2) }}</div>
            <div class="stat-label">Pending Sales Value</div>
        </div>
    </div>

</div>

{{-- Report navigation cards --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem;">

    {{-- Stock on Hand --}}
    <a href="{{ route('reports.stock') }}" class="card" style="text-decoration:none;transition:all .2s;border-color:var(--border);"
       onmouseover="this.style.borderColor='#6366f1';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon purple" style="flex-shrink:0;"><i class="fas fa-boxes-stacked"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">Stock on Hand</div>
                <div style="font-size:.82rem;color:var(--muted);">Current quantity available per product per warehouse</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Low Stock --}}
    <a href="{{ route('reports.low-stock') }}" class="card" style="text-decoration:none;transition:all .2s;"
       onmouseover="this.style.borderColor='#f59e0b';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon amber" style="flex-shrink:0;"><i class="fas fa-triangle-exclamation"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">
                    Low Stock
                    @if($summary['low_stock_count'] > 0)
                        <span class="badge badge-amber" style="margin-left:.4rem;">{{ $summary['low_stock_count'] }}</span>
                    @endif
                </div>
                <div style="font-size:.82rem;color:var(--muted);">Products below minimum stock threshold</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Expiry Report --}}
    <a href="{{ route('reports.expiry') }}" class="card" style="text-decoration:none;transition:all .2s;"
       onmouseover="this.style.borderColor='#ef4444';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon red" style="flex-shrink:0;"><i class="fas fa-calendar-xmark"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">
                    Expiry Report
                    @if($summary['expiring_soon'] > 0)
                        <span class="badge badge-red" style="margin-left:.4rem;">{{ $summary['expiring_soon'] }}</span>
                    @endif
                </div>
                <div style="font-size:.82rem;color:var(--muted);">Batches expiring soon or already expired</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Valuation --}}
    <a href="{{ route('reports.valuation') }}" class="card" style="text-decoration:none;transition:all .2s;"
       onmouseover="this.style.borderColor='#22c55e';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon green" style="flex-shrink:0;"><i class="fas fa-coins"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">Inventory Valuation</div>
                <div style="font-size:.82rem;color:var(--muted);">Cost vs selling value across all warehouses</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Purchase Report --}}
    <a href="{{ route('reports.purchases') }}" class="card" style="text-decoration:none;transition:all .2s;"
       onmouseover="this.style.borderColor='#38bdf8';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon sky" style="flex-shrink:0;"><i class="fas fa-truck"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">Purchase Report</div>
                <div style="font-size:.82rem;color:var(--muted);">All purchase orders filtered by date and status</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Sales Report --}}
    <a href="{{ route('reports.sales') }}" class="card" style="text-decoration:none;transition:all .2s;"
       onmouseover="this.style.borderColor='#6366f1';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon purple" style="flex-shrink:0;"><i class="fas fa-cart-shopping"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">Sales Report</div>
                <div style="font-size:.82rem;color:var(--muted);">All sales orders filtered by date, status, customer</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

    {{-- Movement History --}}
    <a href="{{ route('reports.movements') }}" class="card" style="text-decoration:none;transition:all .2s;grid-column:span 1;"
       onmouseover="this.style.borderColor='#a78bfa';this.style.transform='translateY(-3px)'"
       onmouseout="this.style.borderColor='var(--border)';this.style.transform='translateY(0)'">
        <div style="display:flex;align-items:center;gap:1rem;">
            <div class="stat-icon purple" style="flex-shrink:0;"><i class="fas fa-clock-rotate-left"></i></div>
            <div>
                <div style="font-weight:700;margin-bottom:.25rem;">Movement History</div>
                <div style="font-size:.82rem;color:var(--muted);">Full audit trail of every stock movement</div>
            </div>
            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--muted);"></i>
        </div>
    </a>

</div>

@endsection