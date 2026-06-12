@extends('layouts.app')
@section('title', 'Platform Dashboard')

@push('styles')
<style>
    .health-row{display:flex;align-items:center;justify-content:space-between;padding:.65rem 0;border-bottom:1px solid var(--border);font-size:.875rem}
    .health-row:last-child{border-bottom:none}
</style>
@endpush

@section('topbar-actions')
    <button class="btn btn-secondary btn-icon" style="position:relative" title="Notifications">
        <i class="fas fa-bell"></i>
        @if($notification_count > 0)
            <span class="notif-badge">{{ $notification_count }}</span>
        @endif
    </button>
@endsection

@section('content')

@php
$hour     = (int) now()->format('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', auth()->user()->name)[0];
@endphp

<div class="dash-greeting">
    <h1>{{ $greeting }}, {{ $firstName }}!</h1>
    <p>{{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Platform overview</p>
</div>

{{-- ── TENANT METRICS ──────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-building"></i> &nbsp;Tenants</p>
<div class="kpi-grid">
    <x-kpi-card title="Total Tenants"   value="{{ number_format($total_tenants) }}"          icon="building"     color="green" />
    <x-kpi-card title="Active"          value="{{ number_format($active_tenants) }}"          icon="circle-check" color="green" />
    <x-kpi-card title="Suspended"       value="{{ number_format($suspended_tenants) }}"       icon="pause-circle" color="{{ $suspended_tenants > 0 ? 'orange' : 'primary' }}" />
    <x-kpi-card title="New This Month"  value="{{ number_format($new_tenants_this_month) }}"  icon="user-plus"    color="blue" />
</div>

{{-- ── USER METRICS ────────────────────────────────────────────────────── --}}
<p class="kpi-section-title"><i class="fas fa-users"></i> &nbsp;Users</p>
<div class="kpi-grid">
    <x-kpi-card title="Total Users"   value="{{ number_format($total_users) }}"   icon="users"      color="primary" />
    <x-kpi-card title="Active Users"  value="{{ number_format($active_users) }}"  icon="user-check" color="green" />
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
                    <tr><th>Name</th><th>Slug</th><th>Status</th><th>Joined</th></tr>
                </thead>
                <tbody>
                    @foreach($recent_tenants as $tenant)
                    <tr>
                        <td style="font-weight:600">{{ $tenant->name }}</td>
                        <td style="color:var(--muted);font-size:.8rem;font-family:monospace">{{ $tenant->slug }}</td>
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
