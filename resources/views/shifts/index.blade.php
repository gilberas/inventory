@extends('layouts.dashboard')
@section('title', 'Shifts')
@section('breadcrumb', 'Employees / Shifts')

@section('content')
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
    {{-- Create shift --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">New Shift</h2></div>
        <div style="padding:1rem">
            <form method="POST" action="{{ route('shifts.store') }}">
                @csrf
                <div style="margin-bottom:.75rem"><label>Shift Name *</label><input type="text" name="name" placeholder="Morning, Evening..." required></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem">
                    <div><label>Start Time *</label><input type="time" name="start_time" required></div>
                    <div><label>End Time *</label><input type="time" name="end_time" required></div>
                </div>
                @if($errors->any())<div class="alert alert-danger" style="margin-bottom:.75rem">{{ $errors->first() }}</div>@endif
                <button type="submit" class="btn btn-primary">Create Shift</button>
            </form>
        </div>
    </div>

    {{-- Shift list --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">All Shifts</h2></div>
        <div class="table-wrapper"><table>
            <thead><tr><th>Name</th><th>Start</th><th>End</th><th></th></tr></thead>
            <tbody>
                @forelse($shifts as $shift)
                <tr>
                    <td><strong>{{ $shift->name }}</strong></td>
                    <td>{{ $shift->start_time }}</td>
                    <td>{{ $shift->end_time }}</td>
                    <td>
                        <button onclick="document.getElementById('edit-{{ $shift->id }}').style.display='block'" class="btn btn-secondary btn-sm">Edit</button>
                    </td>
                </tr>
                <tr id="edit-{{ $shift->id }}" style="display:none;background:var(--bg-alt)">
                    <td colspan="4" style="padding:.75rem">
                        <form method="POST" action="{{ route('shifts.update', $shift) }}" style="display:flex;gap:.5rem;align-items:flex-end">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $shift->name }}" style="width:140px">
                            <input type="time" name="start_time" value="{{ $shift->start_time }}" style="width:110px">
                            <input type="time" name="end_time" value="{{ $shift->end_time }}" style="width:110px">
                            <button class="btn btn-primary btn-sm">Save</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" style="text-align:center;color:var(--muted)">No shifts yet.</td></tr>
                @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
