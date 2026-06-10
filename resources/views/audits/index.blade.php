@extends('layouts.app')
@section('title', 'Inventory Audits')
@section('breadcrumb', 'Inventory / Audits')

@section('topbar-actions')
    <button onclick="document.getElementById('initiateModal').classList.remove('hidden')"
            class="btn btn-primary btn-sm">
        <i class="fas fa-clipboard-check"></i> Initiate Audit
    </button>
@endsection

@section('content')

{{-- Flash --}}
@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

{{-- Initiate Modal --}}
<div id="initiateModal" class="hidden" style="position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:50;display:flex;align-items:center;justify-content:center">
    <div class="card" style="width:100%;max-width:480px;margin:1rem">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-clipboard-check" style="color:var(--primary)"></i> Initiate Stock Audit</h2>
            <button onclick="document.getElementById('initiateModal').classList.add('hidden')" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="{{ route('audits.store') }}">
            @csrf
            <div class="form-group" style="margin-bottom:1rem">
                <label>Warehouse *</label>
                <select name="warehouse_id" required>
                    <option value="">Select warehouse...</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group" style="margin-bottom:1.5rem">
                <label>Audit Date *</label>
                <input type="date" name="audit_date" value="{{ date('Y-m-d') }}" required>
            </div>
            @if($errors->any())
                <div class="alert alert-danger" style="margin-bottom:1rem">
                    @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
                </div>
            @endif
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-clipboard-check"></i> Initiate
                </button>
                <button type="button" onclick="document.getElementById('initiateModal').classList.add('hidden')" class="btn btn-secondary">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    {{-- Filters --}}
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <select name="status" onchange="this.form.submit()" style="width:auto;min-width:150px">
                <option value="">All Statuses</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="branch_id" onchange="this.form.submit()" style="width:auto;min-width:160px">
                <option value="">All Branches</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Warehouse</th>
                    <th>Audit Date</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Initiated By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($audits as $audit)
                @php
                    $colors = ['initiated'=>'badge-amber','counting'=>'badge-sky','completed'=>'badge-purple','posted'=>'badge-green'];
                    $color = $colors[$audit->status] ?? 'badge-gray';
                @endphp
                <tr>
                    <td style="font-family:monospace;color:var(--muted)">#{{ $audit->id }}</td>
                    <td>{{ $audit->warehouse?->name ?? '—' }}</td>
                    <td>{{ $audit->audit_date?->format('d M Y') }}</td>
                    <td>{{ $audit->items_count ?? '—' }}</td>
                    <td><span class="badge {{ $color }}">{{ ucfirst($audit->status) }}</span></td>
                    <td style="color:var(--muted)">{{ $audit->initiatedBy?->name ?? '—' }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('audits.show', $audit) }}" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            @if(! $audit->isPosted())
                                <a href="{{ route('audits.sheet', $audit) }}" class="btn btn-primary btn-sm btn-icon" title="Count Sheet"><i class="fas fa-list-check"></i></a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-clipboard-check"></i>
                            <h3>No audits found</h3>
                            <p>Initiate a stock audit to count and reconcile inventory.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $audits->withQueryString()->links() }}</div>
</div>

@if($errors->any())
<script>document.getElementById('initiateModal').classList.remove('hidden');</script>
@endif
@endsection
