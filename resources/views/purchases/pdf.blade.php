<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f5f5f5; }
        .text-right { text-align: right; }
        .header { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>PURCHASE ORDER — {{ $po->reference_no }}</h2>
        <p><strong>Date:</strong> {{ $po->order_date?->format('d M Y') }}</p>
        <p><strong>Expected Delivery:</strong> {{ $po->expected_date?->format('d M Y') ?? '—' }}</p>
        <p><strong>Status:</strong> {{ $po->status }}</p>
    </div>

    <h3>Supplier</h3>
    <p>{{ $po->supplier->name ?? '—' }}<br>
       {{ $po->supplier->email ?? '' }}<br>
       {{ $po->supplier->phone ?? '' }}</p>

    <h3>Items</h3>
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th class="text-right">Qty Ordered</th>
                <th class="text-right">Unit Cost</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->items as $item)
            <tr>
                <td>{{ $item->product->name ?? $item->product_id }}</td>
                <td class="text-right">{{ $item->quantity_ordered }}</td>
                <td class="text-right">{{ number_format($item->unit_cost, 2) }}</td>
                <td class="text-right">{{ number_format($item->total_cost, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><strong>Total</strong></td>
                <td class="text-right"><strong>{{ number_format($po->total_amount, 2) }}</strong></td>
            </tr>
        </tfoot>
    </table>

    @if ($po->notes)
    <p><strong>Notes:</strong> {{ $po->notes }}</p>
    @endif
</body>
</html>
