@extends('layouts.dashboard')
@section('title', 'Sales Report')
@section('breadcrumb', 'Reports / Sales')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:140px;">
            <label style="display:block;margin-bottom:.4rem;">From Date</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div style="flex:1;min-width:140px;">
            <label style="display:block;margin-bottom:.4rem;">To Date</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <div style="flex:1;min-width:160px;">
            <label style="display:block;margin-bottom:.4rem;">Status</label>
            <select name="status">
                <option value="">All Statuses</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="{{ route('reports.sales') }}" class="btn btn-secondary btn-sm">Clear</a>
    </form>
</div>

{{-- Total --}}
<div class="stat-card" style="margin-bottom:1.25rem;">
    <div class="stat-icon green"><i class="fas fa-cart-shopping"></i></div>
    <div>
        <div class="stat-value" style="color:var(--success);">${{ number_format($totalAmount, 2) }}</div>
        <div class="stat-label">Total Sales Amount — {{ $orders->count() }} orders</div>
    </div>
</div>

{{-- Table --}}
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Customer</th>
                    <th>Warehouse</th>
                    <th>Order Date</th>
                    <th>Delivery</th>
                    <th>Status</th>
                    <th style="text-align:right;">Amount</th>
                    <th>Created By</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                <tr>
                    <td>
                        <a href="{{ route('sales.show', $order) }}" style="font-family:monospace;font-size:.85rem;color:var(--primary);text-decoration:none;">
                            {{ $order->reference_no }}
                        </a>
                    </td>
                    <td style="font-weight:500;">{{ $order->customer->name }}</td>
                    <td style="color:var(--muted);">{{ $order->warehouse->name }}</td>
                    <td>{{ $order->order_date->format('d M Y') }}</td>
                    <td style="color:var(--muted);">{{ $order->expected_delivery?->format('d M Y') ?? '—' }}</td>
                    <td>
                        @php
                            $sc = match($order->status) {
                                'DELIVERED'  => 'badge-green',
                                'SHIPPED'    => 'badge-sky',
                                'CONFIRMED','PROCESSING' => 'badge-amber',
                                'CANCELLED'  => 'badge-red',
                                default      => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $sc }}">{{ \App\Models\SalesOrder::STATUSES[$order->status] }}</span>
                    </td>
                    <td style="text-align:right;font-weight:700;color:var(--success);">${{ number_format($order->total_amount, 2) }}</td>
                    <td style="color:var(--muted);font-size:.82rem;">{{ $order->createdBy->name }}</td>
                    <td>
                        <a href="{{ route('sales.show', $order) }}" class="btn btn-secondary btn-icon btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9">
                        <div class="empty-state">
                            <i class="fas fa-cart-shopping"></i>
                            <h3>No sales orders found</h3>
                            <p>Try adjusting your date range or filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
