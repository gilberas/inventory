@extends('layouts.app')
@section('title', 'Warehouses')
@section('breadcrumb', 'Inventory / Warehouses')
@section('topbar-actions')
    <a href="{{ route('warehouses.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> New Warehouse
    </a>
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Location</th><th>Locations</th><th>Default</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($warehouses as $warehouse)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $warehouse->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $warehouse->address ?? '—' }}</td>
                    <td>{{ $warehouse->locations_count ?? 0 }}</td>
                    <td>
                        @if($warehouse->is_default)
                            <span class="badge badge-purple"><i class="fas fa-star"></i> Default</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge {{ $warehouse->is_active ? 'badge-green' : 'badge-gray' }}">
                            {{ $warehouse->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('warehouses.show', $warehouse) }}" class="btn btn-secondary btn-sm btn-icon" title="View"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary btn-sm btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" onsubmit="return confirm('Delete warehouse?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state"><i class="fas fa-building"></i><h3>No warehouses yet</h3>
                    <a href="{{ route('warehouses.create') }}" class="btn btn-primary btn-sm" style="margin-top:.75rem">Add Warehouse</a></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
