<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; margin: 0; padding: 16px; }
    .header { border-bottom: 2px solid #6366f1; margin-bottom: 12px; padding-bottom: 8px; }
    .header h1 { font-size: 14pt; color: #6366f1; margin: 0 0 4px; }
    .meta { font-size: 8pt; color: #64748b; }
    .totals { display: flex; gap: 16px; margin: 12px 0; }
    .total-box { border: 1px solid #e2e8f0; padding: 8px 12px; border-radius: 4px; flex: 1; }
    .total-box .val { font-size: 13pt; font-weight: bold; color: #6366f1; }
    .total-box .lbl { font-size: 7pt; color: #64748b; }
    table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    th { background: #6366f1; color: #fff; padding: 5px 6px; text-align: left; font-size: 8pt; }
    td { border-bottom: 1px solid #e2e8f0; padding: 4px 6px; font-size: 8pt; }
    h3 { font-size: 10pt; margin: 14px 0 4px; color: #334155; }
</style>
</head>
<body>
@php $d = $data; @endphp
<div class="header">
    <h1>Daily Sales Report — {{ $d['date'] }}</h1>
    <div class="meta">
        @if(!empty($tenant)) {{ $tenant->name }} &nbsp;|&nbsp; @endif
        Generated: {{ $generated_at }}
    </div>
</div>

<div class="totals">
    <div class="total-box"><div class="val">{{ number_format($d['totals']->revenue ?? 0, 2) }}</div><div class="lbl">Revenue (TZS)</div></div>
    <div class="total-box"><div class="val">{{ $d['totals']->transactions ?? 0 }}</div><div class="lbl">Transactions</div></div>
    <div class="total-box"><div class="val">{{ number_format($d['totals']->discounts ?? 0, 2) }}</div><div class="lbl">Discounts</div></div>
</div>

<h3>Sales by Product</h3>
<table>
    <thead><tr><th>Product</th><th>SKU</th><th>Units Sold</th><th>Revenue</th></tr></thead>
    <tbody>
        @forelse($d['byProduct'] as $r)
        <tr><td>{{ $r->name }}</td><td>{{ $r->sku }}</td><td>{{ $r->units_sold }}</td><td>{{ number_format($r->revenue, 2) }}</td></tr>
        @empty
        <tr><td colspan="4" style="text-align:center;color:#94a3b8">No sales</td></tr>
        @endforelse
    </tbody>
</table>

<h3>Sales by Payment Method</h3>
<table>
    <thead><tr><th>Method</th><th>Total</th><th>Count</th></tr></thead>
    <tbody>
        @foreach($d['byPayment'] as $r)
        <tr><td>{{ strtoupper($r->payment_method) }}</td><td>{{ number_format($r->total, 2) }}</td><td>{{ $r->count }}</td></tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
