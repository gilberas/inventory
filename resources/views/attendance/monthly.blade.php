@extends('layouts.dashboard')
@section('title', $employee->name . ' — Monthly Attendance')
@section('breadcrumb', 'Attendance / Monthly Report')

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem;display:flex;gap:.75rem;align-items:flex-end">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
        <div><label>Month</label><input type="month" name="month" value="{{ $month }}"></div>
        <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
    <a href="{{ route('employees.show', $employee) }}" class="btn btn-secondary btn-sm">Back</a>
</div></div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1rem">
    <div class="card" style="padding:1.25rem;text-align:center"><div style="font-size:2rem;font-weight:700;color:var(--success)">{{ $summary['present'] }}</div><div style="color:var(--muted)">Present</div></div>
    <div class="card" style="padding:1.25rem;text-align:center"><div style="font-size:2rem;font-weight:700;color:var(--danger)">{{ $summary['absent'] }}</div><div style="color:var(--muted)">Absent</div></div>
    <div class="card" style="padding:1.25rem;text-align:center"><div style="font-size:2rem;font-weight:700;color:var(--warning)">{{ $summary['late'] }}</div><div style="color:var(--muted)">Late</div></div>
    <div class="card" style="padding:1.25rem;text-align:center"><div style="font-size:2rem;font-weight:700">{{ $summary['half_day'] }}</div><div style="color:var(--muted)">Half Day</div></div>
</div>

<div class="card">
    <div class="card-header"><h2 class="card-title">{{ $employee->name }} — {{ \Carbon\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Date</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th><th>Note</th></tr></thead>
        <tbody>
            @forelse($records as $r)
            @php
                $hours = $r->clock_out ? round($r->clock_in->diffInMinutes($r->clock_out) / 60, 1) : null;
                $colors = ['present'=>'badge-green','late'=>'badge-yellow','absent'=>'badge-red','half_day'=>'badge-gray'];
            @endphp
            <tr>
                <td>{{ $r->date->format('d M Y') }}</td>
                <td>{{ $r->clock_in->format('H:i') }}</td>
                <td>{{ $r->clock_out?->format('H:i') ?? '—' }}</td>
                <td>{{ $hours ?? '—' }}</td>
                <td><span class="badge {{ $colors[$r->status] ?? 'badge-gray' }}">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span></td>
                <td style="color:var(--muted)">{{ $r->note ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="6" style="text-align:center;color:var(--muted)">No records for this month.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</div>
@endsection
