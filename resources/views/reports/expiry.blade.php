@extends('layouts.dashboard')
@section('title', 'Expiry Report')
@section('breadcrumb', 'Reports / Expiry')

@section('topbar-actions')
    <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left"></i> Back
    </a>
@endsection

@section('content')

{{-- Days filter --}}
<div class="card" style="margin-bottom:1.25rem;">
    <form method="GET" style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;">
        <div>
            <label style="display:block;margin-bottom:.4rem;">Show items expiring within</label>
            <select name="days" style="width:180px;">
                <option value="7"  {{ $days == 7  ? 'selected' : '' }}>7 days</option>
                <option value="14" {{ $days == 14 ? 'selected' : '' }}>14 days</option>
                <option value="30" {{ $days == 30 ? 'selected' : '' }}>30 days</option>
                <option value="60" {{ $days == 60 ? 'selected' : '' }}>60 days</option>
                <option value="90" {{ $days == 90 ? 'selected' : '' }}>90 days</option>
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
                        <th>Batch No.</th>
                        <th style="text-align:right;">Qty</th>
                        <th>Expiry Date</th>
                        <th>Days Left</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expiringSoon as $batch)
                    @php $daysLeft = now()->diffInDays($batch->expiry_date, false); @endphp
                    <tr>
                        <td>
                            <a href="{{ route('products.show', $batch->product) }}" style="color:var(--primary);text-decoration:none;font-weight:500;">
                                {{ $batch->product->name }}
                            </a>
                            <div style="font-size:.75rem;font-family:monospace;color:var(--muted);">{{ $batch->product->sku }}</div>
                        </td>
                        <td><span class="badge badge-gray">{{ $batch->batch_number }}</span></td>
                        <td style="text-align:right;">{{ number_format($batch->quantity, 2) }} {{ $batch->product->unit?->abbreviation }}</td>
                        <td>{{ $batch->expiry_date->format('d M Y') }}</td>
                        <td>
                            <span style="font-weight:700;color:{{ $daysLeft <= 7 ? 'var(--danger)' : 'var(--warning)' }};">
                                {{ $daysLeft }} days
                            </span>
                        </td>
                        <td>
                            @if($daysLeft <= 7)
                                <span class="badge badge-red"><i class="fas fa-circle-exclamation"></i> Critical</span>
                            @else
                                <span class="badge badge-amber"><i class="fas fa-clock"></i> Expiring Soon</span>
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
                    <tr>
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
