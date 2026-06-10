@extends('layouts.dashboard')
@section('title', $employee->name)
@section('breadcrumb', 'Employees / ' . $employee->name)

@section('topbar-actions')
    <a href="{{ route('employees.performance', $employee) }}" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar"></i> Performance</a>
    <a href="{{ route('employees.schedule', $employee) }}" class="btn btn-secondary btn-sm"><i class="fas fa-calendar"></i> Schedule</a>
    <a href="{{ route('employees.edit', $employee) }}" class="btn btn-primary btn-sm"><i class="fas fa-edit"></i> Edit</a>
@endsection

@section('content')
@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:1rem">{{ session('error') }}</div>
@endif

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    {{-- Profile --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Profile</h2></div>
        <div style="padding:1rem">
            <table style="width:100%">
                <tr><td style="color:var(--muted);width:40%">Name</td><td><strong>{{ $employee->name }}</strong></td></tr>
                <tr><td style="color:var(--muted)">Branch</td><td>{{ $employee->branch?->name ?? '—' }}</td></tr>
                <tr><td style="color:var(--muted)">Department</td><td>{{ $employee->department ?? '—' }}</td></tr>
                <tr><td style="color:var(--muted)">Position</td><td>{{ $employee->position ?? '—' }}</td></tr>
                <tr><td style="color:var(--muted)">Phone</td><td>{{ $employee->phone }}</td></tr>
                <tr><td style="color:var(--muted)">Email</td><td>{{ $employee->email ?? '—' }}</td></tr>
                <tr><td style="color:var(--muted)">Salary</td><td>{{ $employee->salary ? number_format($employee->salary, 2) . ' TZS' : '—' }}</td></tr>
                <tr><td style="color:var(--muted)">Join Date</td><td>{{ $employee->join_date->format('d M Y') }}</td></tr>
                <tr><td style="color:var(--muted)">Status</td><td><span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-gray' }}">{{ ucfirst($employee->status) }}</span></td></tr>
                <tr><td style="color:var(--muted)">Linked Account</td><td>{{ $employee->user?->name ?? 'None' }}</td></tr>
            </table>
        </div>
    </div>

    {{-- Attendance summary --}}
    <div class="card">
        <div class="card-header"><h2 class="card-title">Attendance Summary</h2></div>
        <div style="padding:1rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;text-align:center">
            <div><div style="font-size:2rem;font-weight:700;color:var(--success)">{{ $presentDays }}</div><div style="color:var(--muted)">Present Days</div></div>
            <div><div style="font-size:2rem;font-weight:700;color:var(--danger)">{{ $absentDays }}</div><div style="color:var(--muted)">Absent Days</div></div>
            <div><div style="font-size:2rem;font-weight:700">{{ $totalDays }}</div><div style="color:var(--muted)">Total Recorded</div></div>
            <div><div style="font-size:2rem;font-weight:700;color:var(--primary)">{{ $attendancePct }}%</div><div style="color:var(--muted)">Attendance Rate</div></div>
        </div>
        <div style="padding:.75rem 1rem;border-top:1px solid var(--border);display:flex;gap:.5rem">
            <form method="POST" action="{{ route('attendance.clock-in', $employee) }}">
                @csrf <button class="btn btn-primary btn-sm">Clock In</button>
            </form>
            <form method="POST" action="{{ route('attendance.clock-out', $employee) }}">
                @csrf <button class="btn btn-secondary btn-sm">Clock Out</button>
            </form>
            <a href="{{ route('attendance.monthly', $employee) }}" class="btn btn-secondary btn-sm">Monthly Report</a>
        </div>
    </div>
</div>

{{-- Recent attendance --}}
<div class="card">
    <div class="card-header"><h2 class="card-title">Recent Attendance (last 30 days)</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
            @forelse($employee->attendance as $att)
            <tr>
                <td>{{ $att->date->format('d M Y') }}</td>
                <td>{{ $att->clock_in->format('H:i') }}</td>
                <td>{{ $att->clock_out?->format('H:i') ?? '—' }}</td>
                <td>
                    @php $colors = ['present'=>'badge-green','late'=>'badge-yellow','absent'=>'badge-red','half_day'=>'badge-gray']; @endphp
                    <span class="badge {{ $colors[$att->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_', ' ', $att->status)) }}</span>
                </td>
                <td style="color:var(--muted)">{{ $att->note ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:var(--muted)">No attendance records yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
