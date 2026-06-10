@extends('layouts.app')
@section('title', 'Audit #' . $audit->id)
@section('breadcrumb', 'Inventory / Audits / #' . $audit->id)

@section('topbar-actions')
    @if(! $audit->isPosted())
        <a href="{{ route('audits.sheet', $audit) }}" class="btn btn-primary btn-sm">
            <i class="fas fa-list-check"></i> Count Sheet
        </a>
        <a href="{{ route('audits.variance', $audit) }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-chart-bar"></i> Variance Report
        </a>
    @endif
    @if(in_array($audit->status, ['counting', 'completed']))
        <form method="POST" action="{{ route('audits.post', $audit) }}" style="display:inline"
              onsubmit="return confirm('Post audit and apply all adjustments?')">
            @csrf
            <button class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Post Audit</button>
        </form>
    @endif
@endsection

@section('content')
@php
    $colors = ['initiated'=>'badge-amber','counting'=>'badge-sky','completed'=>'badge-purple','posted'=>'badge-green'];
    $color = $colors[$audit->status] ?? 'badge-gray';
    $steps = ['initiated','counting','completed','posted'];
    $currentStep = array_search($audit->status, $steps);
@endphp

@if(session('success'))
    <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
@endif

{{-- Status tracker --}}
<div class="card" style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem">
        @foreach($steps as $si => $step)
        @php $done = $currentStep !== false && $si <= $currentStep; @endphp
        <div style="display:flex;align-items:center;gap:.5rem;flex:1;min-width:110px">
            <div style="width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;
                background:{{ $done ? 'var(--primary)' : 'var(--border)' }};
                color:{{ $done ? '#fff' : 'var(--muted)' }}">{{ $si + 1 }}</div>
            <div style="font-size:.8rem;font-weight:600;color:{{ $done ? 'var(--text)' : 'var(--muted)' }}">{{ ucfirst($step) }}</div>
            @if($si < count($steps) - 1)
                <div style="flex:1;height:2px;background:{{ ($done && $currentStep > $si) ? 'var(--primary)' : 'var(--border)' }};margin:0 .5rem"></div>
            @endif
        </div>
        @endforeach
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-file-lines" style="color:var(--primary)"></i> Audit Details</h2>
            <span class="badge {{ $color }}">{{ ucfirst($audit->status) }}</span>
        </div>
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <tr><td style="padding:.4rem 0;color:var(--muted);width:140px">Audit ID</td><td style="font-family:monospace">#{{ $audit->id }}</td></tr>
            <tr><td style="padding:.4rem 0;color:var(--muted)">Warehouse</td><td><strong>{{ $audit->warehouse?->name }}</strong></td></tr>
            <tr><td style="padding:.4rem 0;color:var(--muted)">Audit Date</td><td>{{ $audit->audit_date?->format('d M Y') }}</td></tr>
            <tr><td style="padding:.4rem 0;color:var(--muted)">Initiated By</td><td>{{ $audit->initiatedBy?->name }}</td></tr>
            @if($audit->approvedBy)<tr><td style="padding:.4rem 0;color:var(--muted)">Posted By</td><td>{{ $audit->approvedBy?->name }}</td></tr>@endif
            <tr><td style="padding:.4rem 0;color:var(--muted)">Items</td><td>{{ $audit->items->count() }} products</td></tr>
        </table>
    </div>
    <div class="card">
        <div class="card-header">
            <h2 class="card-title"><i class="fas fa-chart-pie" style="color:var(--accent)"></i> Progress</h2>
        </div>
        @php
            $total   = $audit->items->count();
            $counted = $audit->items->whereNotNull('physical_qty')->count();
            $pct     = $total > 0 ? round($counted / $total * 100) : 0;
        @endphp
        <div style="margin-bottom:.75rem">
            <div style="display:flex;justify-content:space-between;font-size:.85rem;margin-bottom:.35rem">
                <span>{{ $counted }} / {{ $total }} items counted</span>
                <span style="font-weight:600">{{ $pct }}%</span>
            </div>
            <div style="height:8px;background:var(--border);border-radius:4px">
                <div style="height:100%;width:{{ $pct }}%;background:var(--primary);border-radius:4px;transition:width .3s"></div>
            </div>
        </div>
        @if($audit->status === 'completed')
            <div style="padding:.6rem;background:rgba(34,197,94,.1);border-radius:.5rem;font-size:.85rem;color:var(--accent)">
                <i class="fas fa-check-circle"></i> All items counted — ready to post
            </div>
        @endif
    </div>
</div>

{{-- Items table --}}
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-list" style="color:var(--primary)"></i> Audit Items</h2>
        @if(! $audit->isPosted())
            <a href="{{ route('audits.sheet', $audit) }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-edit"></i> Enter Counts
            </a>
        @endif
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>System Qty</th>
                    <th>Physical Qty</th>
                    <th>Variance</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                @foreach($audit->items as $item)
                @php $v = (float) $item->variance; @endphp
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;color:var(--muted)">{{ $item->product->sku }}</td>
                    <td><strong>{{ $item->product->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $item->product->unit?->abbreviation ?? '—' }}</td>
                    <td>{{ number_format((float) $item->system_qty, 2) }}</td>
                    <td>{{ $item->physical_qty !== null ? number_format((float) $item->physical_qty, 2) : '<span style="color:var(--muted)">—</span>' }}</td>
                    <td>
                        @if($item->physical_qty !== null)
                            <span style="color:{{ $v > 0 ? 'var(--accent)' : ($v < 0 ? 'var(--danger)' : 'var(--muted)') }};font-weight:600">
                                {{ $v > 0 ? '+' : '' }}{{ number_format($v, 2) }}
                            </span>
                        @else
                            <span style="color:var(--muted)">—</span>
                        @endif
                    </td>
                    <td style="color:var(--muted);font-size:.85rem">{{ $item->notes ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
