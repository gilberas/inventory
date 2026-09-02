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
    th    { background: #333; color: #fff; padding: 4px 6px; text-align: left; }
    td    { padding: 3px 6px; border-bottom: 1px solid #eee; }
    .right  { text-align: right; }
    .subtotal { background: #f5f5f5; font-weight: bold; }
    .total    { background: #e0e0e0; font-weight: bold; font-size: 12px; }
    .neg      { color: #c00; }
</style>
</head>
<body>

<div class="header">
    <h1>{{ $tenant->name ?? 'N/A' }}</h1>
    <div class="meta">
        TIN: {{ $tenant->tin ?? ($tenant->config['tin'] ?? 'N/A') }} &nbsp;|&nbsp;
        Branch: {{ $branchId ? "Branch #{$branchId}" : 'All Branches' }} &nbsp;|&nbsp;
        Period: {{ $startDate }} &ndash; {{ $endDate }} &nbsp;|&nbsp;
        Generated: {{ now()->format('d M Y H:i') }}
    </div>
</div>

<h1>Income Statement</h1>

<h3>Revenue</h3>
<table>
    <tr><td>Gross Revenue</td><td class="right">{{ number_format($data['revenue'], 2) }}</td></tr>
    <tr><td>Sales Returns</td><td class="right neg">({{ number_format($data['returns'], 2) }})</td></tr>
    <tr class="subtotal"><td>Net Revenue</td><td class="right">{{ number_format($data['netRevenue'], 2) }}</td></tr>
    <tr><td>Cost of Goods Sold (COGS)</td><td class="right neg">({{ number_format($data['cogs'], 2) }})</td></tr>
    <tr class="subtotal"><td>Gross Profit</td><td class="right">{{ number_format($data['grossProfit'], 2) }}</td></tr>
    <tr><td>Gross Margin</td><td class="right">{{ $data['grossMargin'] }}%</td></tr>
</table>

<h3>Operating Expenses</h3>
<table>
    @foreach($data['opexByCategory'] as $category => $amount)
    <tr><td>{{ $category }}</td><td class="right">{{ number_format($amount, 2) }}</td></tr>
    @endforeach
    <tr class="subtotal"><td>Total Operating Expenses</td><td class="right">{{ number_format($data['totalOpex'], 2) }}</td></tr>
</table>

<table>
    <tr class="total">
        <td>NET PROFIT</td>
        <td class="right {{ $data['netProfit'] < 0 ? 'neg' : '' }}">
            TZS {{ number_format($data['netProfit'], 2) }}
        </td>
    </tr>
</table>

</body>
</html>
