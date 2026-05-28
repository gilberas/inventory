@extends('layouts.app')
@section('title', 'Transfer ' . $transfer->reference_no)
@section('breadcrumb', 'Inventory / Transfers / ' . $transfer->reference_no)

@section('topbar-actions')
    @if($transfer->status === 'PENDING')
        <form method="POST" action="{{ route('transfers.approve', $transfer) }}" style="display:inline">
            @csrf
            <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
        </form>
        <form method="POST" action="{{ route('transfers.cancel', $transfer) }}" style="display:inline"
              onsubmit="return confirm('Cancel this transfer?')">
            @csrf
            <button class="btn btn-danger btn-sm"><i class="fas fa-times"></i> Cancel</button>
        </form>
    @endif

    @if($transfer->status === 'APPROVED')
        <form method="POST" action="{{ route('transfers.dispatch', $transfer) }}" style="display:inline">
            @csrf
            <button class="btn btn-primary btn-sm"><i class="fas fa-truck-fast"></i> Dispatch</button>
        </form>
    @endif

    @if($transfer->status === 'IN_TRANSIT')
        <form method="POST" action="{{ route('transfers.receive', $transfer) }}" style="display:inline">
            @csrf
            <button class="btn btn-success btn-sm"><i class="fas fa-box-open"></i> Mark Received</button>
        </form>
    @endif
@endsection

@section('content')
@php
    $statusColors = [
        'PENDING'    => 'badge-amber',
        'APPROVED'   => 'badge-sky',
        'IN_TRANSIT' => 'badge-purple',
        'COMPLETED'  => 'badge-green',
        'CANCELLED'  => 'badge-red',
    ];
    $color = $statusColors[$transfer->status] ?? 'badge-gray';

    $steps = ['PENDING', 'APPROVED', 'IN_TRANSIT', 'COMPLETED'];
    $currentStep = array_search($transfer->status, $steps);
@endphp

{{-- Status tracker --}}
<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        @foreach($steps as $si => $step)
        @php $done = $currentStep !== false && $si <= $currentStep; @endphp
        <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:120px">
            <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;
                background:{{ $done ? 'var(--primary)' : 'var(--border)' }};
                color:{{ $done ? '#fff' : 'var(--muted)' }}">
                {{ $si + 1 }}
            </div>
            <div>
                <div style="font-size:.8rem;font-weight:600;color:{{ $done ? 'var(--text)' : 'var(--muted)' }}">
                    {{ ucfirst(strtolower(str_replace('_', ' ', $step))) }}
                </div>
            </div>
            @if($si < count($steps) - 1)
                <div style="flex:1;height:2px;background:{{ $done && $currentStep > $si ? 'var(--primary)' : 'var(--border)' }};margin:0 .5rem"></div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">

    {{-- Transfer info --}}
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-file-lines" style="color:var(--primary)"></i> Transfer Details</h2>
            <span class="badge {{ $color }}">
                {{ ucfirst(strtolower(str_replace('_', ' ', $transfer->status))) }}
            </span>
        </div>
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <tr>
                <td style="padding:.5rem 0;color:var(--muted);width:140px">Reference</td>
                <td style="font-family:monospace;font-weight:600">{{ $transfer->reference_no }}</td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">From Warehouse</td>
                <td><strong>{{ $transfer->fromWarehouse->name }}</strong></td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">To Warehouse</td>
                <td><strong>{{ $transfer->toWarehouse->name }}</strong></td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Transfer Date</td>
                <td>{{ $transfer->transfer_date?->format('d M Y') }}</td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Created By</td>
                <td>{{ $transfer->createdBy->name ?? '—' }}</td>
            </tr>
            @if($transfer->approvedBy)
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Approved By</td>
                <td>{{ $transfer->approvedBy->name }}</td>
            </tr>
            @endif
            @if($transfer->notes)
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Notes</td>
                <td style="color:var(--muted)">{{ $transfer->notes }}</td>
            </tr>
            @endif
        </table>
    </div>

    {{-- Summary --}}
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-pie" style="color:var(--accent)"></i> Summary</h2>
        </div>
        <div class="stats-grid" style="grid-template-columns:1fr 1fr;margin-bottom:0">
            <div class="stat-card" style="padding:1rem">
                <div class="stat-icon purple"><i class="fas fa-boxes-stacked"></i></div>
                <div>
                    <div class="stat-value">{{ $transfer->items->count() }}</div>
                    <div class="stat-label">Product Lines</div>
                </div>
            </div>
            <div class="stat-card" style="padding:1rem">
                <div class="stat-icon green"><i class="fas fa-cubes"></i></div>
                <div>
                    <div class="stat-value">{{ number_format($transfer->items->sum('quantity'), 0) }}</div>
                    <div class="stat-label">Total Units</div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Items --}}
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-list" style="color:var(--primary)"></i> Transfer Items</h2>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @foreach($transfer->items as $item)
                <tr>
                    <td style="color:var(--muted)">{{ $loop->iteration }}</td>
                    <td style="font-family:monospace;color:var(--muted);font-size:.8rem">
                        {{ $item->product->sku }}
                    </td>
                    <td><strong>{{ $item->product->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $item->product->unit->abbreviation ?? '—' }}</td>
                    <td><strong>{{ number_format($item->quantity, 2) }}</strong></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
