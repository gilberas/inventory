@extends('layouts.app')
@section('title', 'Platform Dashboard')

@push('styles')
<style>
    .kpi-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.875rem; }
    .kpi-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.1rem 1.25rem; display:flex; flex-direction:column; gap:.35rem; }
    .kpi-icon { font-size:.9rem; margin-bottom:.2rem; }
    .kpi-value { font-size:1.55rem; font-weight:800; line-height:1; }
    .kpi-label { font-size:.75rem; color:var(--muted); }
    .kpi-card.success { border-color:rgba(34,197,94,.4); }
    .kpi-card.warning { border-color:rgba(245,158,11,.4); }
    .kpi-card.danger  { border-color:rgba(239,68,68,.4); }
    .kpi-section-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); margin:1.5rem 0 .75rem; }
    .chart-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.25rem; margin-top:1.25rem; }
    .chart-card h3 { font-size:.875rem; font-weight:700; margin-bottom:1rem; }
    .health-row { display:flex; align-items:center; justify-content:space-between; padding:.6rem 0; border-bottom:1px solid var(--border); font-size:.875rem; }
    .health-row:last-child { border-bottom:none; }
</style>
@endpush

@section('topbar-actions')
    <button class="btn btn-secondary btn-icon" style="position:relative" title="Notifications">
        <i class="fas fa-bell"></i>
        @if($notification_count > 0)
            <span style="position:absolute;top:-4px;right:-4px;background:var(--danger);color:#fff;border-radius:999px;font-size:.65rem;font-weight:700;min-width:16px;height:16px;display:flex;align-items:center;justify-content:center;padding:0 3px;">{{ $notification_count }}</span>
        @endif
    </button>
@endsection

@section('content')

{{-- ── TENANT METRICS ──────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-building"></i> &nbsp;Tenants</p>
<div class="kpi-grid">
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-building"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($total_tenants) }}</span>
        <span class="kpi-label">Total Tenants</span>
    </div>
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-circle-check"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($active_tenants) }}</span>
        <span class="kpi-label">Active</span>
    </div>
    <div class="kpi-card {{ $suspended_tenants > 0 ? 'warning' : '' }}">
        <span class="kpi-icon" style="color:var(--warning)"><i class="fas fa-pause-circle"></i></span>
        <span class="kpi-value">{{ number_format($suspended_tenants) }}</span>
        <span class="kpi-label">Suspended</span>
    </div>
    <div class="kpi-card">
        <span class="kpi-icon" style="color:var(--info)"><i class="fas fa-user-plus"></i></span>
        <span class="kpi-value">{{ number_format($new_tenants_this_month) }}</span>
        <span class="kpi-label">New This Month</span>
    </div>
</div>

{{-- ── USER METRICS ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-users"></i> &nbsp;Users</p>
<div class="kpi-grid">
    <div class="kpi-card">
        <span class="kpi-icon" style="color:var(--primary)"><i class="fas fa-users"></i></span>
        <span class="kpi-value">{{ number_format($total_users) }}</span>
        <span class="kpi-label">Total Users</span>
    </div>
    <div class="kpi-card success">
        <span class="kpi-icon" style="color:var(--success)"><i class="fas fa-user-check"></i></span>
        <span class="kpi-value" style="color:var(--success)">{{ number_format($active_users) }}</span>
        <span class="kpi-label">Active Users</span>
    </div>
</div>

{{-- ── SYSTEM HEALTH ───────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-server"></i> &nbsp;System Health</p>
<div class="chart-card">
    <div class="health-row">
        <span><i class="fas fa-code-branch" style="color:var(--primary)"></i> &nbsp;Laravel</span>
        <span class="badge badge-purple">v{{ app()->version() }}</span>
    </div>
    <div class="health-row">
        <span><i class="fab fa-php" style="color:var(--info)"></i> &nbsp;PHP</span>
        <span class="badge badge-sky">{{ PHP_VERSION }}</span>
    </div>
    <div class="health-row">
        <span><i class="fas fa-list-check" style="color:var(--warning)"></i> &nbsp;Jobs in Queue</span>
        <span class="badge {{ $queue_size > 0 ? 'badge-amber' : 'badge-green' }}">{{ number_format($queue_size) }}</span>
    </div>
    <div class="health-row">
        <span><i class="fas fa-circle-exclamation" style="color:var(--danger)"></i> &nbsp;Failed Jobs</span>
        <span class="badge {{ $failed_jobs > 0 ? 'badge-red' : 'badge-green' }}">{{ number_format($failed_jobs) }}</span>
    </div>
    <div class="health-row">
        <span><i class="fas fa-database" style="color:var(--success)"></i> &nbsp;Environment</span>
        <span class="badge badge-gray">{{ app()->environment() }}</span>
    </div>
</div>

{{-- ── RECENT TENANTS ──────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-clock-rotate-left"></i> &nbsp;Recent Tenants</p>
<div class="chart-card">
    @if($recent_tenants->isEmpty())
        <div class="empty-state" style="padding:2rem 0">
            <i class="fas fa-building"></i>
            <p>No tenants yet.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Status</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recent_tenants as $tenant)
                    <tr>
                        <td style="font-weight:600">{{ $tenant->name }}</td>
                        <td class="font-mono" style="color:var(--muted);font-size:.8rem">{{ $tenant->slug }}</td>
                        <td>
                            <span class="badge {{ $tenant->status === 'active' ? 'badge-green' : 'badge-amber' }}">
                                {{ ucfirst($tenant->status) }}
                            </span>
                        </td>
                        <td style="color:var(--muted);font-size:.8rem">
                            {{ \Carbon\Carbon::parse($tenant->created_at)->format('d M Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
