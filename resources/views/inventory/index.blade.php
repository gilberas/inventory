@extends('layouts.app')
@section('title', 'Stock Levels')
@section('breadcrumb', 'Inventory / Stock Levels')
@section('topbar-actions')
    <a href="{{ route('inventory.adjustment') }}" class="btn btn-primary btn-sm"><i class="fas fa-sliders"></i> Adjust Stock</a>
@endsection
@section('content')
<div class="card">
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <div class="search-input">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search by product or SKU..." value="{{ request('search') }}">
            </div>
            <select name="warehouse_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id')==$wh->id?'selected':'' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </form>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>SKU</th><th>Product</th><th>Warehouse</th><th>Qty</th><th>Min Stock</th><th>Unit</th><th>Status</th></tr>
            </thead>
            <tbody>
                @forelse($balances as $balance)
                <tr>
                    <td style="color:var(--muted);font-family:monospace">{{ $balance->product->sku }}</td>
                    <td><strong>{{ $balance->product->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $balance->warehouse->name }}</td>
                    <td><strong>{{ number_format($balance->quantity, 2) }}</strong></td>
                    <td style="color:var(--muted)">{{ $balance->product->minimum_stock }}</td>
                    <td style="color:var(--muted)">{{ $balance->product->unit->abbreviation ?? '—' }}</td>
                    <td>
                        @if($balance->quantity <= 0)
                            <span class="badge badge-red"><i class="fas fa-circle-xmark"></i> Out of Stock</span>
                        @elseif($balance->quantity <= $balance->product->minimum_stock)
                            <span class="badge badge-amber"><i class="fas fa-triangle-exclamation"></i> Low Stock</span>
                        @else
                            <span class="badge badge-green"><i class="fas fa-circle-check"></i> In Stock</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state"><i class="fas fa-warehouse"></i><h3>No stock records yet</h3><p>Stock is updated when you receive goods or make adjustments.</p></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $balances->withQueryString()->links() }}</div>
</div>
@endsection
