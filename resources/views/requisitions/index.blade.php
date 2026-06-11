@extends('layouts.app')
@section('title', 'Purchase Requisitions')
@section('breadcrumb', 'Purchasing / Requisitions')
@section('topbar-actions')
    @can('purchases.create')
    <a href="{{ route('requisitions.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Requisition</a>
    @endcan
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Requested By</th>
                    <th>Notes</th>
                    <th>Items</th>
                    <th>Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requisitions as $req)
                <tr>
                    <td>{{ $requisitions->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $req->requestedBy?->name ?? '—' }}</strong></td>
                    <td style="color:var(--muted)">{{ $req->notes ? \Illuminate\Support\Str::limit($req->notes, 50) : '—' }}</td>
                    <td>{{ $req->items_count ?? $req->items->count() }}</td>
                    <td style="color:var(--muted)">{{ $req->created_at->format('d M Y') }}</td>
                    <td>
                        @php
                            $statusMap = [
                                'draft'               => ['badge-amber',  'Draft'],
                                'pending'             => ['badge-blue',   'Pending'],
                                'approved'            => ['badge-green',  'Approved'],
                                'rejected'            => ['badge-red',    'Rejected'],
                                'revision_requested'  => ['badge-orange', 'Needs Revision'],
                                'converted'           => ['badge-purple', 'Converted'],
                            ];
                            [$cls, $label] = $statusMap[$req->status] ?? ['badge-amber', ucfirst($req->status)];
                        @endphp
                        <span class="badge {{ $cls }}">{{ $label }}</span>
                    </td>
                    <td>
                        <a href="{{ route('requisitions.show', $req) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-eye"></i></a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No requisitions yet</h3>
                        <p>Requisitions allow staff to request stock replenishment for manager approval.</p>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $requisitions->withQueryString()->links() }}</div>
</div>
@endsection
