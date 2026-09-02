@extends('layouts.dashboard')
@section('title', $employee->name . ' — Schedule')
@section('breadcrumb', 'Employees / ' . $employee->name . ' / Schedule')

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem;display:flex;gap:.75rem;align-items:flex-end">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end">
        <div><label>Month</label><input type="month" name="month" value="{{ $month }}"></div>
        <button type="submit" class="btn btn-primary btn-sm">Go</button>
    </form>
    <a href="{{ route('employees.show', $employee) }}" class="btn btn-secondary btn-sm">Back</a>
</div></div>

<div class="card">
    <div class="card-header"><h2 class="card-title">{{ $employee->name }} — {{ \Carbon\Carbon::createFromDate($year, $mon, 1)->format('F Y') }}</h2></div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Date</th><th>Day</th><th>Shift</th><th>Status</th></tr></thead>
        <tbody>
            @for($d = 1; $d <= $daysInMonth; $d++)
            @php $dateStr = sprintf('%04d-%02d-%02d', $year, $mon, $d); $entry = $shifts[$dateStr] ?? null; @endphp
            <tr>
                <td>{{ $dateStr }}</td>
                <td style="color:var(--muted)">{{ \Carbon\Carbon::parse($dateStr)->format('D') }}</td>
                <td>{{ $entry?->shift?->name ?? '—' }}</td>
                <td>
                    @if($entry)
                    @php $colors=['scheduled'=>'badge-gray','present'=>'badge-green','absent'=>'badge-red','late'=>'badge-yellow']; @endphp
                    <span class="badge {{ $colors[$entry->status] ?? 'badge-gray' }}">{{ ucfirst($entry->status) }}</span>
                    @else
                    <span style="color:var(--muted)">Unassigned</span>
                    @endif
                </td>
            </tr>
            @endfor
        </tbody>
    </table></div>
</div>
@endsection
