<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1   { font-size: 16px; margin-bottom: 2px; }
    h3   { font-size: 12px; margin: 10px 0 4px; border-bottom: 1px solid #999; padding-bottom: 3px; }
    .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
    .meta   { color: #555; font-size: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    td    { padding: 3px 6px; border-bottom: 1px solid #eee; }
    th    { background: #555; color: #fff; padding: 4px 6px; }
    .right   { text-align: right; }
    .subtotal { background: #f5f5f5; font-weight: bold; }
    .total    { background: #e0e0e0; font-weight: bold; font-size: 12px; }
    .indent  { padding-left: 20px; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $tenant->name ?? 'N/A' }}</h1>
    <div class="meta">
        TIN: {{ $tenant->tin ?? ($tenant->config['tin'] ?? 'N/A') }} &nbsp;|&nbsp;
        Branch: {{ $branchId ? "Branch #{$branchId}" : 'All Branches' }} &nbsp;|&nbsp;
        As at: {{ $endDate }} &nbsp;|&nbsp;
        Generated: {{ now()->format('d M Y H:i') }}
    </div>
</div>

<h1>Balance Sheet</h1>

<h3>Assets</h3>
<table>
    <tr><th colspan="2">Inventory</th></tr>
    @foreach($data['inventoryByWarehouse'] as $warehouse => $value)
    <tr><td class="indent">{{ $warehouse }}</td><td class="right">{{ number_format($value, 2) }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Inventory</td><td class="right">{{ number_format($data['inventoryTotal'], 2) }}</td></tr>
    <tr><td>Cash &amp; Equivalents</td><td class="right">{{ number_format($data['cashAssets'], 2) }}</td></tr>
    <tr class="total"><td>TOTAL ASSETS</td><td class="right">{{ number_format($data['totalAssets'], 2) }}</td></tr>
</table>

<h3>Liabilities</h3>
<table>
    <tr><td>Supplier Payables</td><td class="right">{{ number_format($data['payables'], 2) }}</td></tr>
    <tr><td>Customer Advances</td><td class="right">{{ number_format($data['customerAdvances'], 2) }}</td></tr>
    <tr class="subtotal"><td>Total Liabilities</td><td class="right">{{ number_format($data['totalLiabilities'], 2) }}</td></tr>
</table>

<h3>Equity</h3>
<table>
    <tr><td>Retained Earnings / Equity</td><td class="right">{{ number_format($data['equity'], 2) }}</td></tr>
    <tr class="total"><td>TOTAL LIABILITIES + EQUITY</td>
        <td class="right">{{ number_format($data['totalLiabilities'] + $data['equity'], 2) }}</td></tr>
</table>

</body>
</html>
