@extends('layouts.app')
@section('title', 'Transaction Log')
@section('breadcrumb', 'Inventory / Transactions')
@section('content')
<div class="card">
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <select name="type" onchange="this.form.submit()" style="width:auto;min-width:150px">
                <option value="">All Types</option>
                <option value="purchase"   {{ request('type')=='purchase'?'selected':'' }}>Purchase</option>
                <option value="sale"       {{ request('type')=='sale'?'selected':'' }}>Sale</option>
                <option value="adjustment" {{ request('type')=='adjustment'?'selected':'' }}>Adjustment</option>
                <option value="transfer"   {{ request('type')=='transfer'?'selected':'' }}>Transfer</option>
                <option value="return"     {{ request('type')=='return'?'selected':'' }}>Return</option>
            </select>
            <select name="warehouse_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('warehouse_id')==$wh->id?'selected':'' }}>{{ $wh->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Ref</th><th>Type</th><th>Warehouse</th><th>Items</th><th>Date</th><th>By</th><th>Notes</th></tr>
            </thead>
            <tbody>
                @forelse($transactions as $txn)
                @php
                    $typeColors = ['purchase'=>'badge-green','sale'=>'badge-sky','adjustment'=>'badge-amber','transfer'=>'badge-purple','return'=>'badge-red'];
                    $color = $typeColors[$txn->type] ?? 'badge-gray';
                @endphp
                <tr>
                    <td style="font-family:monospace;color:var(--muted)">{{ $txn->reference ?? '#'.$txn->id }}</td>
                    <td><span class="badge {{ $color }}">{{ ucfirst($txn->type) }}</span></td>
                    <td>{{ $txn->warehouse->name ?? '—' }}</td>
                    <td>{{ $txn->items_count ?? $txn->items->count() }}</td>
                    <td style="color:var(--muted)">{{ $txn->date?->format('d M Y H:i') ?? $txn->created_at->format('d M Y H:i') }}</td>
                    <td style="color:var(--muted)">{{ $txn->createdBy->name ?? 'System' }}</td>
                    <td style="color:var(--muted);max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $txn->notes ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state"><i class="fas fa-right-left"></i><h3>No transactions yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $transactions->withQueryString()->links() }}</div>
</div>
@endsection
