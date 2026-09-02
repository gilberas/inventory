@extends('layouts.dashboard')
@section('title', 'Audit Variance Report')
@section('breadcrumb', 'Reports / Audit Variance')

@section('topbar-actions')
    @if($auditId)
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="btn btn-secondary btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
    @endif
@endsection

@section('content')
<div class="card" style="margin-bottom:1rem"><div style="padding:.75rem 1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end">
        <div><label>Select Audit</label>
            <select name="audit_id">
                <option value="">Choose audit...</option>
                @foreach($audits as $a)
                <option value="{{ $a->id }}" {{ $auditId == $a->id ? 'selected' : '' }}>
                    #{{ $a->id }} — {{ $a->warehouse?->name }} ({{ $a->audit_date?->format('d M Y') }}) — {{ ucfirst($a->status) }}
                </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">View</button>
    </form>
</div></div>

@if($audit)
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Audit #{{ $audit->id }} — {{ $audit->warehouse?->name }}</h2>
        <span class="badge badge-{{ $audit->status === 'posted' ? 'green' : 'amber' }}">{{ ucfirst($audit->status) }}</span>
    </div>
    <div class="table-wrapper"><table>
        <thead><tr><th>Product</th><th>SKU</th><th>System Qty</th><th>Physical Qty</th><th>Variance</th><th>Notes</th></tr></thead>
        <tbody>
            @forelse($auditItems as $r)
            @php $v = (float) $r->variance; @endphp
            <tr>
                <td><strong>{{ $r->name }}</strong></td>
                <td style="font-family:monospace;font-size:.8rem">{{ $r->sku }}</td>
                <td>{{ number_format($r->system_qty, 2) }}</td>
                <td>{{ number_format($r->physical_qty, 2) }}</td>
                <td style="font-weight:600;color:{{ $v > 0 ? 'var(--accent)' : ($v < 0 ? 'var(--danger)' : 'var(--muted)') }}">
                    {{ $v > 0 ? '+' : '' }}{{ number_format($v, 2) }}
                </td>
                <td style="font-size:.85rem;color:var(--muted)">{{ $r->notes ?? '—' }}</td>
            </tr>
            @empty<tr><td colspan="6" style="text-align:center;color:var(--muted)">No counted items.</td></tr>@endforelse
        </tbody>
    </table></div>
</div>
@else
<div class="card"><div style="padding:2rem;text-align:center;color:var(--muted)">Select an audit above to view its variance report.</div></div>
@endif
@endsection
