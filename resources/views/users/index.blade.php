@extends('layouts.app')
@section('title', 'Users')
@section('breadcrumb', 'Admin / Users')
@section('topbar-actions')
    @can('manage users')
    <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
        <i class="fas fa-plus"></i> New User
    </a>
    @endcan
@endsection
@section('content')
<div class="card">
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <div class="search-input">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search users..." value="{{ request('search') }}">
            </div>
            <select name="role" onchange="this.form.submit()" style="width:auto;min-width:140px">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->name }}" {{ request('role')==$role->name?'selected':'' }}>{{ $role->name }}</option>
                @endforeach
            </select>
        </form>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>{{ $users->firstItem() + $loop->index }}</td>
                    <td>
                        <div style="display:flex;align-items:center;gap:.6rem">
                            <div class="avatar" style="width:32px;height:32px;font-size:.8rem">{{ strtoupper(substr($user->name,0,1)) }}</div>
                            {{ $user->name }}
                        </div>
                    </td>
                    <td style="color:var(--muted)">{{ $user->email }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge badge-purple">{{ $role->name }}</span>
                        @endforeach
                    </td>
                    <td>
                        <span class="badge {{ ($user->is_active ?? true) ? 'badge-green' : 'badge-red' }}">
                            {{ ($user->is_active ?? true) ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td style="color:var(--muted)">{{ $user->created_at->format('d M Y') }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-secondary btn-sm btn-icon" title="Edit"><i class="fas fa-pen"></i></a>
                            @if($user->id !== auth()->id())
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Delete this user?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state"><i class="fas fa-users"></i><h3>No users found</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $users->withQueryString()->links() }}</div>
</div>
@endsection
