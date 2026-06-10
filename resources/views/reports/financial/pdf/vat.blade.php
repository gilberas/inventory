<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    h1   { font-size: 16px; margin-bottom: 2px; }
    h2   { font-size: 13px; }
    .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
    .meta   { color: #555; font-size: 10px; }
    .tra-box { border: 2px solid #003399; padding: 10px; margin-bottom: 14px; background: #f0f4ff; }
    table { width: 60%; border-collapse: collapse; margin: 0 auto 10px; }
    th    { background: #003399; color: #fff; padding: 5px 8px; }
    td    { padding: 4px 8px; border-bottom: 1px solid #ccc; }
    .right { text-align: right; }
    .positive { color: #080; font-weight: bold; }
    .negative { color: #c00; font-weight: bold; }
    .total    { background: #ffe066; font-weight: bold; font-size: 13px; }
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

<div class="tra-box">
    <h2>TRA VAT Return</h2>
    <p><strong>Reference No:</strong> {{ $sequentialRef ?? 'N/A' }}</p>
    <p><strong>Tax Period:</strong> {{ $startDate }} to {{ $endDate }}</p>
    <p><strong>Taxpayer:</strong> {{ $tenant->name ?? 'N/A' }}</p>
    <p><strong>TIN:</strong> {{ $tenant->tin ?? ($tenant->config['tin'] ?? 'N/A') }}</p>
</div>

<table>
    <thead>
        <tr><th>Description</th><th class="right">Amount (TZS)</th></tr>
    </thead>
    <tbody>
        <tr>
            <td>Output VAT (VAT Collected)</td>
            <td class="right positive">{{ number_format($data['vatCollected'], 2) }}</td>
        </tr>
        <tr>
            <td>Input VAT (VAT Paid on Purchases)</td>
            <td class="right negative">({{ number_format($data['vatPaid'], 2) }})</td>
        </tr>
    </tbody>
    <tfoot>
        <tr class="total">
            <td>Net VAT Payable to TRA</td>
            <td class="right">{{ number_format($data['netVatPayable'], 2) }}</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
