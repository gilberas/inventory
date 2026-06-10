@extends('layouts.dashboard')
@section('title', 'Edit Employee')
@section('breadcrumb', 'Employees / Edit')

@section('content')
<div class="card" style="max-width:700px">
    <div class="card-header"><h2 class="card-title">Edit — {{ $employee->name }}</h2></div>
    <div style="padding:1.5rem">
        <form method="POST" action="{{ route('employees.update', $employee) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
                <div><label>Full Name *</label><input type="text" name="name" value="{{ old('name', $employee->name) }}" required></div>
                <div><label>Branch *</label>
                    <select name="branch_id" required>
                        @foreach($branches as $b)<option value="{{ $b->id }}" {{ old('branch_id', $employee->branch_id) == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
                    </select>
                </div>
                <div><label>Department</label><input type="text" name="department" value="{{ old('department', $employee->department) }}"></div>
                <div><label>Position</label><input type="text" name="position" value="{{ old('position', $employee->position) }}"></div>
                <div><label>Phone *</label><input type="text" name="phone" value="{{ old('phone', $employee->phone) }}" required></div>
                <div><label>Email</label><input type="email" name="email" value="{{ old('email', $employee->email) }}"></div>
                <div><label>Salary (TZS)</label><input type="number" name="salary" value="{{ old('salary', $employee->salary) }}" step="0.01" min="0"></div>
                <div><label>Join Date *</label><input type="date" name="join_date" value="{{ old('join_date', $employee->join_date->toDateString()) }}" required></div>
                <div><label>Status *</label>
                    <select name="status" required>
                        <option value="active" {{ old('status', $employee->status) === 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status', $employee->status) === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                </div>
                <div><label>Linked User Account</label>
                    <select name="user_id">
                        <option value="">None</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" {{ old('user_id', $employee->user_id) == $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>@endforeach
                    </select>
                </div>
            </div>
            @if($errors->any())
            <div class="alert alert-danger" style="margin-top:1rem">{{ $errors->first() }}</div>
            @endif
            <div style="margin-top:1.5rem;display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary">Update Employee</button>
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
