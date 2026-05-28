@extends('layouts.app')
@section('title', isset($user) ? 'Edit User' : 'New User')
@section('breadcrumb', 'Admin / Users / ' . (isset($user) ? 'Edit' : 'New'))
@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header">
        <h2 class="card-title">{{ isset($user) ? 'Edit User' : 'Create User' }}</h2>
    </div>

    <form method="POST" action="{{ isset($user) ? route('users.update', $user) : route('users.store') }}">
        @csrf
        @if(isset($user)) @method('PUT') @endif

        <div class="form-grid">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
            </div>
            <div class="form-group">
                <label>Password {{ isset($user) ? '(leave blank to keep)' : '' }}</label>
                <input type="password" name="password" {{ isset($user) ? '' : 'required' }}>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password_confirmation">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" required>
                    <option value="">Select role...</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}"
                            {{ old('role', isset($user) ? $user->roles->first()?->name : '') == $role->name ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if(isset($user))
            <div class="form-group">
                <label>Status</label>
                <select name="is_active">
                    <option value="1" {{ ($user->is_active ?? 1) ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ !($user->is_active ?? 1) ? 'selected' : '' }}>Inactive</option>
                </select>
            </div>
            @endif
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i> {{ isset($user) ? 'Update' : 'Create' }} User
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@endsection
