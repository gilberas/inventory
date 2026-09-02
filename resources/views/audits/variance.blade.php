@extends('layouts.app')
@section('title', 'Variance Report — Audit #' . $audit->id)
@section('breadcrumb', 'Inventory / Audits / #' . $audit->id . ' / Variance')

@section('topbar-actions')
    @if(in_array($audit->status, ['counting', 'completed']))
        <form method="POST" action="{{ route('audits.post', $audit) }}" style="display:inline"
              onsubmit="return confirm('Post audit and apply all adjustments?')">
            @csrf
            <button class="btn btn-success btn-sm"><i class="fas fa-check-double"></i> Post Audit</button>
        </form>
    @endif
@endsection

@section('content')
{{-- Summary cards --}}
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:1rem">
    <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-arrow-up"></i></div>
        <div>
            <div class="stat-value" style="color:var(--accent)">{{ count($overages) }}</div>
            <div class="stat-label">Overages</div>
            <div style="font-size:.8rem;color:var(--muted)">+{{ number_format($summary['total_overages_value'], 2) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red"><i class="fas fa-arrow-down"></i></div>
        <div>
            <div class="stat-value" style="color:var(--danger)">{{ count($shortages) }}</div>
            <div class="stat-label">Shortages</div>
            <div style="font-size:.8rem;color:var(--muted)">{{ number_format($summary['total_shortages_value'], 2) }}</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon {{ $summary['net_variance_value'] >= 0 ? 'green' : 'red' }}"><i class="fas fa-balance-scale"></i></div>
        <div>
            <div class="stat-value">{{ count($matches) }}</div>
            <div class="stat-label">Exact Matches</div>
            <div style="font-size:.8rem;color:var(--muted)">Net: {{ number_format($summary['net_variance_value'], 2) }}</div>
        </div>
    </div>
</div>

@php
    $sections = [
        ['title' => 'Overages (Excess Stock Found)', 'items' => $overages, 'color' => 'var(--accent)', 'icon' => 'fa-arrow-up', 'empty' => 'No overages.'],
        ['title' => 'Shortages (Missing Stock)',      'items' => $shortages, 'color' => 'var(--danger)', 'icon' => 'fa-arrow-down', 'empty' => 'No shortages.'],
        ['title' => 'Matches (No Variance)',          'items' => $matches,  'color' => 'var(--muted)',  'icon' => 'fa-check', 'empty' => 'No exact matches.'],
    ];
@endphp

@foreach($sections as $section)
<div class="card" style="margin-bottom:1rem">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas {{ $section['icon'] }}" style="color:{{ $section['color'] }}"></i>
            {{ $section['title'] }}
            <span class="badge" style="background:var(--border);color:var(--text);margin-left:.5rem">{{ count($section['items']) }}</span>
        </h2>
    </div>
    @if(count($section['items']) > 0)
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Product</th>
                    <th>System Qty</th>
                    <th>Physical Qty</th>
                    <th>Variance</th>
                    <th>Value Impact</th>
                </tr>
            </thead>
            <tbody>
                @foreach($section['items'] as $row)
                <tr>
                    <td style="font-family:monospace;font-size:.8rem;color:var(--muted)">{{ $row['sku'] }}</td>
                    <td><strong>{{ $row['product_name'] }}</strong></td>
                    <td>{{ number_format($row['system_qty'], 2) }}</td>
                    <td>{{ number_format($row['physical_qty'], 2) }}</td>
                    <td style="font-weight:600;color:{{ $row['variance'] > 0 ? 'var(--accent)' : ($row['variance'] < 0 ? 'var(--danger)' : 'var(--muted)') }}">
                        {{ $row['variance'] > 0 ? '+' : '' }}{{ number_format($row['variance'], 2) }}
                    </td>
                    <td style="font-weight:600;color:{{ $row['value_impact'] > 0 ? 'var(--accent)' : ($row['value_impact'] < 0 ? 'var(--danger)' : 'var(--muted)') }}">
                        {{ $row['value_impact'] > 0 ? '+' : '' }}{{ number_format($row['value_impact'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
        <div style="padding:1rem;color:var(--muted);font-size:.875rem"><i class="fas fa-check"></i> {{ $section['empty'] }}</div>
    @endif
</div>
@endforeach
@endsection
