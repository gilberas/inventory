@extends('layouts.dashboard')
@section('title', 'Movement History')
@section('breadcrumb', 'Reports / Movement History')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:2;min-width:180px;">
            <label style="display:block;margin-bottom:.4rem;">Product</label>
            <select name="product_id">
                <option value="">All Products</option>
                @foreach($products as $p)
                    <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                        {{ $p->name }} ({{ $p->sku }})
                    </option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:160px;">
            <label style="display:block;margin-bottom:.4rem;">Warehouse</label>
            <select name="warehouse_id">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:140px;">
            <label style="display:block;margin-bottom:.4rem;">Type</label>
            <select name="type">
                <option value="">All Types</option>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div style="flex:1;min-width:130px;">
            <label style="display:block;margin-bottom:.4rem;">From</label>
            <input type="date" name="from" value="{{ request('from') }}">
        </div>
        <div style="flex:1;min-width:130px;">
            <label style="display:block;margin-bottom:.4rem;">To</label>
            <input type="date" name="to" value="{{ request('to') }}">
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> Filter</button>
        <a href="{{ route('reports.movements') }}" class="btn btn-secondary btn-sm">Clear</a>
    </form>
</div>

{{-- Table --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Stock Movements — {{ $transactions->total() }} records</span>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>Type</th>
                    <th>Warehouse</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th>Notes</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                <tr>
                    <td>
                        <a href="{{ route('inventory.show', $txn) }}" style="font-family:monospace;font-size:.85rem;color:var(--primary);text-decoration:none;">
                            {{ $txn->reference_no }}
                        </a>
                    </td>
                    <td>
                        @php
                            $isIn = in_array($txn->transaction_type, \App\Models\InventoryTransaction::IN_TYPES);
                            $tc   = match($txn->transaction_type) {
                                'PURCHASE','RETURN_IN','TRANSFER_IN' => 'badge-green',
                                'SALE','RETURN_OUT','TRANSFER_OUT'   => 'badge-red',
                                'ADJUSTMENT' => 'badge-sky',
                                'DAMAGE'     => 'badge-amber',
                                default      => 'badge-gray',
                            };
                        @endphp
                        <span class="badge {{ $tc }}">
                            <i class="fas fa-arrow-{{ $isIn ? 'down' : 'up' }}"></i>
                            {{ \App\Models\InventoryTransaction::TYPES[$txn->transaction_type] }}
                        </span>
                    </td>
                    <td>{{ $txn->warehouse->name }}</td>
                    <td>
                        @foreach($txn->items->take(3) as $item)
                            <div style="font-size:.78rem;color:var(--muted);">
                                {{ $item->product->name }}
                                <span style="color:var(--text);">× {{ number_format($item->quantity, 2) }}</span>
                            </div>
                        @endforeach
                        @if($txn->items->count() > 3)
                            <div style="font-size:.75rem;color:var(--muted);">+{{ $txn->items->count() - 3 }} more</div>
                        @endif
                    </td>
                    <td style="color:var(--muted);font-size:.85rem;">
                        {{ $txn->transaction_date->format('d M Y H:i') }}
                    </td>
                    <td style="color:var(--muted);font-size:.82rem;">{{ $txn->createdBy->name }}</td>
                    <td style="color:var(--muted);font-size:.82rem;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        {{ $txn->notes ?? '—' }}
                    </td>
                    <td>
                        <a href="{{ route('inventory.show', $txn) }}" class="btn btn-secondary btn-icon btn-sm">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-clock-rotate-left"></i>
                            <h3>No movements found</h3>
                            <p>Try adjusting your filters</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $transactions->withQueryString()->links() }}
    </div>
</div>

@endsection
