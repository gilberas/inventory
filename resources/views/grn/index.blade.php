@extends('layouts.app')
@section('title', 'Goods Received Notes')
@section('breadcrumb', 'Purchasing / Receive (GRN)')
@section('topbar-actions')
    @can('purchase_orders.receive')
    <a href="{{ route('grn.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New GRN</a>
    @endcan
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Reference</th>
                    <th>Purchase Order</th>
                    <th>Supplier</th>
                    <th>Received By</th>
                    <th>Received At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grns as $grn)
                <tr>
                    <td>{{ $grns->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $grn->reference_no ?? 'GRN-' . $grn->id }}</strong></td>
                    <td style="color:var(--muted)">{{ $grn->purchaseOrder?->po_number ?? '—' }}</td>
                    <td style="color:var(--muted)">{{ $grn->purchaseOrder?->supplier?->name ?? '—' }}</td>
                    <td style="color:var(--muted)">{{ $grn->receivedBy?->name ?? '—' }}</td>
                    <td style="color:var(--muted)">
                        {{ $grn->received_at ? \Carbon\Carbon::parse($grn->received_at)->format('d M Y') : '—' }}
                    </td>
                    <td>
                        @if($grn->status === 'confirmed')
                            <span class="badge badge-green">Confirmed</span>
                        @else
                            <span class="badge badge-amber">Draft</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('grn.show', $grn) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-eye"></i></a>
                            @if($grn->status === 'draft')
                            @can('purchase_orders.receive')
                            <form method="POST" action="{{ route('grn.confirm', $grn) }}" onsubmit="return confirm('Confirm this GRN? Inventory will be updated.')">
                                @csrf
                                <button class="btn btn-primary btn-sm btn-icon" title="Confirm"><i class="fas fa-check"></i></button>
                            </form>
                            @endcan
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-truck-ramp-box"></i>
                        <h3>No goods received notes yet</h3>
                        <p>GRNs are created when stock is received against a purchase order.</p>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $grns->withQueryString()->links() }}</div>
</div>
@endsection
