@extends('layouts.dashboard')
@section('title', 'Employees')
@section('breadcrumb', 'Employees')

@section('topbar-actions')
    <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add Employee</a>
@endsection

@section('content')
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>Search</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Name or department..."></div>
        <div><label>Branch</label>
            <select name="branch_id">
                <option value="">All Branches</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" {{ request('branch_id') == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
            </select>
        </div>
        <div><label>Status</label>
            <select name="status">
                <option value="">All</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Employees ({{ $employees->total() }})</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Name</th><th>Branch</th><th>Department</th><th>Position</th><th>Phone</th><th>Join Date</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($employees as $emp)
            <tr>
                <td><a href="{{ route('employees.show', $emp) }}" style="font-weight:600">{{ $emp->name }}</a>
                    @if($emp->user)<br><small style="color:var(--muted)">{{ $emp->user->email }}</small>@endif
                </td>
                <td>{{ $emp->branch?->name ?? '—' }}</td>
                <td>{{ $emp->department ?? '—' }}</td>
                <td>{{ $emp->position ?? '—' }}</td>
                <td>{{ $emp->phone }}</td>
                <td>{{ $emp->join_date->format('d M Y') }}</td>
                <td><span class="badge {{ $emp->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($emp->status) }}</span></td>
                <td style="white-space:nowrap">
                    <a href="{{ route('employees.edit', $emp) }}" class="btn btn-secondary btn-sm">Edit</a>
                    <form method="POST" action="{{ route('employees.destroy', $emp) }}" style="display:inline" onsubmit="return confirm('Delete employee?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-danger btn-sm">Del</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" style="text-align:center;color:var(--muted)">No employees found.</td></tr>
            @endforelse
        </tbody>
    </table></div>
    <div style="padding:.75rem 1rem">{{ $employees->links() }}</div>
</div>
@endsection
