@extends('layouts.app')
@section('title', 'Branch Stock Transfers')
@section('breadcrumb', 'Inventory / Branch Transfers')

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
            <select name="from_branch_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">From: All Branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('from_branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
            <select name="to_branch_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">To: All Branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('to_branch_id') == $branch->id ? 'selected' : '' }}>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>From Branch</th>
                    <th>To Branch</th>
                    <th>Items</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transfers as $transfer)
                @php
                    $statusColors = [
                        'pending'    => 'badge-amber',
                        'approved'   => 'badge-sky',
                        'dispatched' => 'badge-purple',
                        'received'   => 'badge-green',
                        'rejected'   => 'badge-red',
                    ];
                    $color = $statusColors[$transfer->status] ?? 'badge-gray';
                @endphp
                <tr>
                    <td style="font-family:monospace;color:var(--muted)">{{ $transfer->id }}</td>
                    <td>{{ $transfer->fromBranch?->name ?? '—' }}</td>
                    <td>{{ $transfer->toBranch?->name ?? '—' }}</td>
                    <td>{{ $transfer->items_count ?? 0 }} item(s)</td>
                    <td style="color:var(--muted)">{{ $transfer->created_at->format('d M Y') }}</td>
                    <td><span class="badge {{ $color }}">{{ ucfirst($transfer->status) }}</span></td>
                    <td style="color:var(--muted)">{{ $transfer->requestedBy?->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem;flex-wrap:wrap">
                            <a href="{{ route('transfers.show', $transfer) }}"
                               class="btn btn-secondary btn-sm btn-icon" title="View">
                                <i class="fas fa-eye"></i>
                            </a>

                            @if($transfer->status === 'pending')
                                <form method="POST" action="{{ route('transfers.approve', $transfer) }}">
                                    @csrf
                                    <button class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('transfers.reject', $transfer) }}">
                                    @csrf
                                    <input type="hidden" name="reason" value="Rejected by manager">
                                    <button class="btn btn-danger btn-sm">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            @endif

                            @if($transfer->status === 'dispatched')
                                <a href="{{ route('transfers.show', $transfer) }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-box-open"></i> Receive
                                </a>
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
                            <p>Create a transfer to move stock between branches.</p>
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
