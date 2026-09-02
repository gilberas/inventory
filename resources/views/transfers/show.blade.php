@extends('layouts.app')
@section('title', 'Transfer #' . $transfer->id)
@section('breadcrumb', 'Inventory / Transfers / #' . $transfer->id)

@section('topbar-actions')
    @if($transfer->status === 'pending')
        <form method="POST" action="{{ route('transfers.approve', $transfer) }}" style="display:inline">
            @csrf
            <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Approve</button>
        </form>
    @endif

    @if(in_array($transfer->status, ['pending']))
        <form method="POST" action="{{ route('transfers.destroy', $transfer) }}" style="display:inline"
              onsubmit="return confirm('Delete this transfer request?')">
            @csrf @method('DELETE')
            <button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Delete</button>
        </form>
    @endif
@endsection

@section('content')
@php
    $statusColors = [
        'pending'    => 'badge-amber',
        'approved'   => 'badge-sky',
        'dispatched' => 'badge-purple',
        'received'   => 'badge-green',
        'rejected'   => 'badge-red',
    ];
    $color = $statusColors[$transfer->status] ?? 'badge-gray';
    $steps = ['pending', 'approved', 'dispatched', 'received'];
    $currentStep = array_search($transfer->status, $steps);
    if ($transfer->status === 'rejected') $currentStep = 0;
@endphp

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger" style="margin-bottom:1rem">
        @foreach($errors->all() as $error) <div>{{ $error }}</div> @endforeach
    </div>
@endif

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
                    {{ ucfirst($step) }}
                </div>
            </div>
            @if($si < count($steps) - 1)
                <div style="flex:1;height:2px;background:{{ ($done && $currentStep !== false && $currentStep > $si) ? 'var(--primary)' : 'var(--border)' }};margin:0 .5rem"></div>
            @endif
        </div>
        @endforeach

        @if($transfer->status === 'rejected')
            <span class="badge badge-red" style="margin-left:auto">Rejected</span>
        @endif
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">

    {{-- Transfer info --}}
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-file-lines" style="color:var(--primary)"></i> Transfer Details</h2>
            <span class="badge {{ $color }}">{{ ucfirst($transfer->status) }}</span>
        </div>
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <tr>
                <td style="padding:.5rem 0;color:var(--muted);width:140px">Transfer ID</td>
                <td style="font-family:monospace;font-weight:600">#{{ $transfer->id }}</td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">From Branch</td>
                <td><strong>{{ $transfer->fromBranch?->name ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">To Branch</td>
                <td><strong>{{ $transfer->toBranch?->name ?? '—' }}</strong></td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Requested By</td>
                <td>{{ $transfer->requestedBy?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Created</td>
                <td>{{ $transfer->created_at->format('d M Y H:i') }}</td>
            </tr>
            @if($transfer->approvedBy)
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Approved By</td>
                <td>{{ $transfer->approvedBy->name }}</td>
            </tr>
            @endif
            @if($transfer->dispatched_at)
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Dispatched At</td>
                <td>{{ $transfer->dispatched_at->format('d M Y H:i') }}</td>
            </tr>
            @endif
            @if($transfer->received_at)
            <tr>
                <td style="padding:.5rem 0;color:var(--muted)">Received At</td>
                <td>{{ $transfer->received_at->format('d M Y H:i') }}</td>
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
                    <div class="stat-value">{{ number_format($transfer->items->sum('qty_requested'), 0) }}</div>
                    <div class="stat-label">Units Requested</div>
                </div>
            </div>
        </div>

        @if($transfer->status === 'received' && $transfer->hasDiscrepancy())
            <div style="margin-top:1rem;padding:.75rem;background:rgba(239,68,68,.08);border-radius:.5rem;border:1px solid rgba(239,68,68,.3)">
                <div style="color:var(--danger);font-weight:600;font-size:.85rem">
                    <i class="fas fa-triangle-exclamation"></i> Quantity Discrepancy Detected
                </div>
                <div style="font-size:.8rem;color:var(--muted);margin-top:.25rem">
                    Received quantities differ from dispatched amounts. Both branches have been notified.
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Items table --}}
<div class="card" style="margin-bottom:1rem">
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
                    <th>Qty Requested</th>
                    <th>Qty Dispatched</th>
                    <th>Qty Received</th>
                    @if($transfer->status === 'received') <th>Status</th> @endif
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
                    <td style="color:var(--muted)">{{ $item->product->unit?->abbreviation ?? '—' }}</td>
                    <td>{{ number_format($item->qty_requested, 2) }}</td>
                    <td>{{ $item->qty_dispatched !== null ? number_format($item->qty_dispatched, 2) : '—' }}</td>
                    <td>{{ $item->qty_received  !== null ? number_format($item->qty_received,  2) : '—' }}</td>
                    @if($transfer->status === 'received')
                    <td>
                        @if($item->hasDiscrepancy())
                            <span class="badge badge-red"><i class="fas fa-triangle-exclamation"></i> Discrepancy</span>
                        @else
                            <span class="badge badge-green"><i class="fas fa-check"></i> OK</span>
                        @endif
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Pending: reject form --}}
@if($transfer->status === 'pending')
<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-times-circle" style="color:var(--danger)"></i> Reject Transfer</h2>
    </div>
    <form method="POST" action="{{ route('transfers.reject', $transfer) }}">
        @csrf
        <div class="form-group" style="margin-bottom:1rem">
            <label>Rejection Reason *</label>
            <textarea name="reason" required placeholder="Explain why this transfer is being rejected..."></textarea>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-danger" onclick="return confirm('Reject this transfer?')">
                <i class="fas fa-times"></i> Reject Transfer
            </button>
        </div>
    </form>
</div>
@endif

{{-- Approved: dispatch form with per-item quantities --}}
@if($transfer->status === 'approved')
<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-truck-fast" style="color:var(--primary)"></i> Dispatch — Enter Actual Quantities</h2>
    </div>
    <form method="POST" action="{{ route('transfers.dispatch', $transfer) }}">
        @csrf
        <div class="table-wrapper" style="margin-bottom:1rem">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Requested</th>
                        <th>Qty to Dispatch *</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $i => $item)
                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                    <tr>
                        <td><strong>{{ $item->product->name }}</strong></td>
                        <td>{{ number_format($item->qty_requested, 2) }} {{ $item->product->unit?->abbreviation }}</td>
                        <td>
                            <input type="number" name="items[{{ $i }}][qty_dispatched]"
                                   value="{{ old("items.{$i}.qty_dispatched", $item->qty_requested) }}"
                                   min="0.01" step="0.01" required style="width:120px">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-truck-fast"></i> Dispatch Transfer
            </button>
        </div>
    </form>
</div>
@endif

{{-- Dispatched: receive form with per-item quantities --}}
@if($transfer->status === 'dispatched')
<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-box-open" style="color:var(--accent)"></i> Receive — Enter Quantities Actually Received</h2>
    </div>
    <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
        @csrf
        <div class="table-wrapper" style="margin-bottom:1rem">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Dispatched</th>
                        <th>Qty Received *</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $i => $item)
                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                    <tr>
                        <td><strong>{{ $item->product->name }}</strong></td>
                        <td>{{ number_format($item->qty_dispatched ?? 0, 2) }} {{ $item->product->unit?->abbreviation }}</td>
                        <td>
                            <input type="number" name="items[{{ $i }}][qty_received]"
                                   value="{{ old("items.{$i}.qty_received", $item->qty_dispatched) }}"
                                   min="0" step="0.01" required style="width:120px">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-success">
                <i class="fas fa-box-open"></i> Confirm Receipt
            </button>
        </div>
    </form>
</div>
@endif
@endsection
