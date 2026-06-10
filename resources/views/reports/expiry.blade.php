@extends('layouts.dashboard')
@section('title', 'Expiry Report')
@section('breadcrumb', 'Reports / Expiry')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
@endif

{{-- Filters --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <label style="display:block;margin-bottom:.4rem;">Expiring within</label>
            <select name="days" style="min-width:160px;">
                @foreach([7, 14, 30, 60, 90] as $d)
                <option value="{{ $d }}" {{ $days == $d ? 'selected' : '' }}>{{ $d }} days</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="display:block;margin-bottom:.4rem;">Category</label>
            <select name="category_id" style="min-width:180px;">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ $categoryId == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="fas fa-filter"></i> Apply
        </button>
    </form>
</div>

{{-- Expiring Soon --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <span class="card-title" style="color:var(--warning);">
            <i class="fas fa-clock"></i>
            Expiring Within {{ $days }} Days ({{ $expiringSoon->count() }})
        </span>
    </div>

    @if($expiringSoon->isEmpty())
        <div class="empty-state" style="padding:2rem;">
            <i class="fas fa-circle-check" style="color:var(--success);opacity:1;font-size:2rem;margin-bottom:.5rem;display:block;"></i>
            <p>No batches expiring within {{ $days }} days.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Batch No.</th>
                        <th style="text-align:right;">Qty</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expiringSoon as $batch)
                    @php
                        $daysLeft = now()->diffInDays($batch->expiry_date, false);
                        $rowStyle = $daysLeft <= 7
                            ? 'background:#fff5f5;border-left:4px solid #ef4444;'
                            : ($daysLeft <= 14
                                ? 'background:#fff7ed;border-left:4px solid #f97316;'
                                : 'background:#fffbeb;border-left:4px solid #f59e0b;');
                        $isPromo = str_contains($batch->notes ?? '', 'Flagged for Promotion');
                    @endphp
                    <tr style="{{ $rowStyle }}">
                        <td>
                            <a href="{{ route('products.show', $batch->product) }}" style="color:var(--primary);text-decoration:none;font-weight:500;">
                                {{ $batch->product->name }}
                            </a>
                            <div style="font-size:.75rem;font-family:monospace;color:var(--muted);">{{ $batch->product->sku }}</div>
                        </td>
                        <td>{{ $batch->product->category?->name ?? '—' }}</td>
                        <td><span class="badge badge-gray">{{ $batch->batch_number }}</span></td>
                        <td style="text-align:right;">{{ number_format($batch->quantity, 2) }} {{ $batch->product->unit?->abbreviation }}</td>
                        <td>{{ $batch->expiry_date->format('d M Y') }}</td>
                        <td>
                            <span style="font-weight:700;color:{{ $daysLeft <= 7 ? '#ef4444' : ($daysLeft <= 14 ? '#f97316' : '#f59e0b') }};">
                                {{ $daysLeft }} days
                            </span>
                        </td>
                        <td>
                            @if($daysLeft <= 7)
                                <span class="badge badge-red"><i class="fas fa-circle-exclamation"></i> Critical</span>
                            @elseif($daysLeft <= 14)
                                <span class="badge badge-orange"><i class="fas fa-exclamation-triangle"></i> Urgent</span>
                            @else
                                <span class="badge badge-amber"><i class="fas fa-clock"></i> Expiring Soon</span>
                            @endif
                        </td>
                        <td>
                            @if($isPromo)
                                <span class="badge badge-green"><i class="fas fa-tag"></i> Promo</span>
                            @else
                                <form method="POST" action="{{ route('reports.expiry.flag-promotion', $batch) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="btn btn-secondary btn-sm" title="Flag for promotion price reduction"
                                            style="font-size:.75rem;padding:.2rem .6rem;">
                                        <i class="fas fa-tag"></i> Flag Promo
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

{{-- Already Expired --}}
<div class="card">
    <div class="card-header">
        <span class="card-title" style="color:var(--danger);">
            <i class="fas fa-calendar-xmark"></i>
            Already Expired ({{ $expired->count() }})
        </span>
    </div>

    @if($expired->isEmpty())
        <div class="empty-state" style="padding:2rem;">
            <p>No expired batches on record.</p>
        </div>
    @else
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Batch No.</th>
                        <th style="text-align:right;">Qty</th>
                        <th>Expired On</th>
                        <th>Days Overdue</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expired as $batch)
                    <tr style="background:#fff5f5;">
                        <td>
                            <a href="{{ route('products.show', $batch->product) }}" style="color:var(--primary);text-decoration:none;font-weight:500;">
                                {{ $batch->product->name }}
                            </a>
                        </td>
                        <td><span class="badge badge-gray">{{ $batch->batch_number }}</span></td>
                        <td style="text-align:right;">{{ number_format($batch->quantity, 2) }} {{ $batch->product->unit?->abbreviation }}</td>
                        <td style="color:var(--danger);">{{ $batch->expiry_date->format('d M Y') }}</td>
                        <td style="color:var(--danger);font-weight:700;">
                            {{ now()->diffInDays($batch->expiry_date) }} days ago
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

@endsection
