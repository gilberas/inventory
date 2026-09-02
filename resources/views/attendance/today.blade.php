@extends('layouts.dashboard')
@section('title', "Today's Attendance")
@section('breadcrumb', 'Attendance / Today')

@section('content')
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:1rem">{{ session('error') }}</div>
@endif

<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
        <div><label>Branch</label>
            <select name="branch_id">
                <option value="">All Branches</option>
                @foreach($branches as $b)<option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->name }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    </form>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">Attendance — {{ today()->format('d F Y') }}</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Employee</th><th>Branch</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($employees as $emp)
            @php $att = $emp->attendance->first(); @endphp
            <tr>
                <td><a href="{{ route('employees.show', $emp) }}">{{ $emp->name }}</a></td>
                <td>{{ $emp->branch?->name ?? '—' }}</td>
                <td>{{ $att?->clock_in?->format('H:i') ?? '—' }}</td>
                <td>{{ $att?->clock_out?->format('H:i') ?? '—' }}</td>
                <td>
                    @if($att)
                    @php $colors=['present'=>'badge-green','late'=>'badge-yellow','absent'=>'badge-red','half_day'=>'badge-gray']; @endphp
                    <span class="badge {{ $colors[$att->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$att->status)) }}</span>
                    @else
                    <span style="color:var(--muted)">Not clocked in</span>
                    @endif
                </td>
                <td style="white-space:nowrap">
                    @if(!$att)
                    <form method="POST" action="{{ route('attendance.clock-in', $emp) }}" style="display:inline">@csrf<button class="btn btn-primary btn-sm">Clock In</button></form>
                    @elseif(!$att->clock_out)
                    <form method="POST" action="{{ route('attendance.clock-out', $emp) }}" style="display:inline">@csrf<button class="btn btn-secondary btn-sm">Clock Out</button></form>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:var(--muted)">No active employees found.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
