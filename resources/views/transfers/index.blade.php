@extends('layouts.app')
@section('title', 'Stock Transfers')
@section('breadcrumb', 'Inventory / Transfers')

@section('topbar-actions')
    <a href="{{ route('transfers.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> New Transfer
    </a>
@endsection

@section('content')
<div class="card">

    {{-- Filters --}}
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <select name="status" onchange="this.form.submit()" style="width:auto;min-width:150px">
                <option value="">All Statuses</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <select name="from_warehouse_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">From: All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('from_warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
            <select name="to_warehouse_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">To: All Warehouses</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ request('to_warehouse_id') == $wh->id ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Reference</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                @php
                    $statusColors = [
                        'PENDING'    => 'badge-amber',
                        'APPROVED'   => 'badge-sky',
                        'IN_TRANSIT' => 'badge-purple',
                        'COMPLETED'  => 'badge-green',
                        'CANCELLED'  => 'badge-red',
                    ];
                    $color = $statusColors[$transfer->status] ?? 'badge-gray';
                @endphp
                <tr>
                    <td style="font-family:monospace;color:var(--muted)">
                        {{ $transfer->reference_no }}
                    </td>
                    <td>{{ $transfer->fromWarehouse->name }}</td>
                    <td>{{ $transfer->toWarehouse->name }}</td>
                    <td>{{ $transfer->items_count }} item(s)</td>
                    <td style="color:var(--muted)">
                        {{ $transfer->transfer_date?->format('d M Y') }}
                    </td>
                    <td>
                        <span class="badge {{ $color }}">
                            {{ $transfer->status_label ?? ucfirst(strtolower(str_replace('_', ' ', $transfer->status))) }}
                        </span>
                    </td>
                    <td style="color:var(--muted)">{{ $transfer->createdBy->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                            <a href="{{ route('transfers.show', $transfer) }}"
                               class="btn btn-secondary btn-sm btn-icon" title="View">
                                <i class="fas fa-eye"></i>
                            </a>

                            @if($transfer->status === 'PENDING')
                                <form method="POST" action="{{ route('transfers.approve', $transfer) }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm" title="Approve">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.cancel', $transfer) }}"
                                      onsubmit="return confirm('Cancel this transfer?')">
                                    @csrf
                                    <button class="btn btn-danger btn-sm btn-icon" title="Cancel">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                            @endif

                            @if($transfer->status === 'APPROVED')
                                <form method="POST" action="{{ route('transfers.dispatch', $transfer) }}">
                                    @csrf
                                    <button class="btn btn-primary btn-sm">
                                        <i class="fas fa-truck-fast"></i> Dispatch
                                    </button>
                                </form>
                            @endif

                            @if($transfer->status === 'IN_TRANSIT')
                                <form method="POST" action="{{ route('transfers.receive', $transfer) }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <i class="fas fa-box-open"></i> Receive
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-truck-fast"></i>
                            <h3>No transfers found</h3>
                            <p>Create a transfer to move stock between warehouses.</p>
                            <a href="{{ route('transfers.create') }}" class="btn btn-primary btn-sm" style="margin-top:.75rem">
                                <i class="fas fa-plus"></i> New Transfer
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrapper">
        {{ $transfers->withQueryString()->links() }}
    </div>
</div>
@endsection
