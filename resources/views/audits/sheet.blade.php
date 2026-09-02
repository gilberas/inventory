@extends('layouts.app')
@section('title', 'Count Sheet — Audit #' . $audit->id)
@section('breadcrumb', 'Inventory / Audits / #' . $audit->id . ' / Count Sheet')

@section('content')
<div class="card" style="margin-bottom:1rem">
    <div style="padding:.75rem 1rem;background:rgba(251,191,36,.1);border-radius:.5rem;border:1px solid rgba(251,191,36,.4);font-size:.875rem">
        <i class="fas fa-triangle-exclamation" style="color:#d97706"></i>
        <strong>Blind Count Sheet</strong> — System quantities are hidden to avoid counting bias.
        Enter the physical count for each product exactly as found.
    </div>
</div>

<form method="POST" action="{{ route('audits.counts', $audit) }}">
    @csrf

    @if($errors->any())
        <div class="alert alert-danger" style="margin-bottom:1rem">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-list-check" style="color:var(--primary)"></i>
                Audit #{{ $audit->id }} — {{ $audit->warehouse?->name }}
                <span style="font-size:.8rem;color:var(--muted);margin-left:.5rem">{{ $audit->audit_date?->format('d M Y') }}</span>
            </h2>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>SKU</th>
                        <th>Product</th>
                        <th>Unit</th>
                        <th style="color:#d97706">System Qty</th>
                        <th>Physical Count *</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $i => $item)
                    <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item['id'] }}">
                    <tr>
                        <td style="color:var(--muted)">{{ $loop->iteration }}</td>
                        <td style="font-family:monospace;font-size:.8rem;color:var(--muted)">{{ $item['sku'] }}</td>
                        <td><strong>{{ $item['product_name'] }}</strong></td>
                        <td style="color:var(--muted)">{{ $item['unit'] ?? '—' }}</td>
                        <td style="color:#d97706;font-size:.85rem;font-style:italic">Hidden</td>
                        <td>
                            <input type="number"
                                   name="items[{{ $i }}][physical_qty]"
                                   value="{{ old("items.{$i}.physical_qty", $item['physical_qty'] ?? '') }}"
                                   min="0" step="0.0001" placeholder="0.00"
                                   style="width:120px" required>
                        </td>
                        <td>
                            <input type="text"
                                   name="items[{{ $i }}][notes]"
                                   value="{{ old("items.{$i}.notes", $item['notes'] ?? '') }}"
                                   placeholder="Optional note..." style="width:200px">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="form-actions" style="padding:1rem">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i> Save Counts
            </button>
            <a href="{{ route('audits.show', $audit) }}" class="btn btn-secondary">Cancel</a>
        </div>
    </div>
</form>
@endsection
