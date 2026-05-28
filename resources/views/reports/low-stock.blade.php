@extends('layouts.dashboard')
@section('title', 'Low Stock Report')
@section('breadcrumb', 'Reports / Low Stock')

@section('topbar-actions')
    <a href="{{ route('purchases.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-file-invoice"></i> Create Purchase Order
    </a>
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

@if($products->isEmpty())
    <div class="card">
        <div class="empty-state">
            <i class="fas fa-circle-check" style="color:var(--success);opacity:1;"></i>
            <h3>All stock levels are healthy!</h3>
            <p>No products are currently below their minimum stock threshold.</p>
        </div>
    </div>
@else
    {{-- Alert banner --}}
    <div class="alert alert-warning" style="margin-bottom:1.25rem;">
        <i class="fas fa-triangle-exclamation"></i>
        <span><strong>{{ $products->count() }} product(s)</strong> are at or below their minimum stock level and require reordering.</span>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Products Requiring Reorder</span>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th style="text-align:right;">Min Stock</th>
                        <th style="text-align:right;">Current Stock</th>
                        <th style="text-align:right;">Shortfall</th>
                        <th>Warehouses</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $product)
                    @php
                        $total     = $product->totalStock();
                        $shortfall = max(0, $product->minimum_stock - $total);
                    @endphp
                    <tr>
                        <td><span style="font-family:monospace;font-size:.8rem;color:var(--muted);">{{ $product->sku }}</span></td>
                        <td>
                            <a href="{{ route('products.show', $product) }}" style="color:var(--primary);text-decoration:none;font-weight:500;">
                                {{ $product->name }}
                            </a>
                        </td>
                        <td style="color:var(--muted);">{{ $product->category?->name ?? '—' }}</td>
                        <td style="color:var(--muted);">{{ $product->unit?->abbreviation ?? '—' }}</td>
                        <td style="text-align:right;">{{ $product->minimum_stock }}</td>
                        <td style="text-align:right;font-weight:700;color:{{ $total <= 0 ? 'var(--danger)' : 'var(--warning)' }};">
                            {{ number_format($total, 2) }}
                        </td>
                        <td style="text-align:right;color:var(--danger);font-weight:600;">
                            {{ $shortfall > 0 ? '−' . number_format($shortfall, 2) : '—' }}
                        </td>
                        <td>
                            @foreach($product->stockBalances as $bal)
                                <div style="font-size:.78rem;color:var(--muted);">
                                    {{ $bal->warehouse->name }}: <span style="color:var(--text);">{{ number_format($bal->quantity_available, 2) }}</span>
                                </div>
                            @endforeach
                        </td>
                        <td>
                            @if($total <= 0)
                                <span class="badge badge-red">Out of Stock</span>
                            @else
                                <span class="badge badge-amber">Low Stock</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@endsection
