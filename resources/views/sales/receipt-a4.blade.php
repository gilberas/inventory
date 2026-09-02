<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 20mm; }
  h1 { font-size: 18px; }
  table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  th, td { border: 1px solid #ccc; padding: 6px 8px; }
  th { background: #f5f5f5; }
  .right { text-align: right; }
  .totals td { border: none; }
  .footer { margin-top: 30px; font-size: 10px; color: #666; }
</style>
</head>
<body>
  <h1>TAX INVOICE / RECEIPT</h1>
  <table class="totals">
    <tr><td><strong>Receipt No:</strong></td><td>{{ $sale->receipt_no }}</td></tr>
    <tr><td><strong>Date:</strong></td><td>{{ $sale->created_at->format('d F Y H:i') }}</td></tr>
    @if($sale->customer)
    <tr><td><strong>Customer:</strong></td><td>{{ $sale->customer->name }}<br>{{ $sale->customer->phone }}</td></tr>
    @endif
    <tr><td><strong>Cashier:</strong></td><td>{{ $sale->cashier->name }}</td></tr>
  </table>

  <table style="margin-top:20px">
    <thead>
      <tr>
        <th>#</th><th>Item</th><th class="right">Qty</th>
        <th class="right">Unit Price</th><th class="right">Discount</th><th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      @foreach($sale->items as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $item->product->name }}</td>
        <td class="right">{{ number_format($item->qty, 2) }}</td>
        <td class="right">{{ number_format($item->unit_price, 2) }}</td>
        <td class="right">{{ number_format($item->discount, 2) }}</td>
        <td class="right">{{ number_format($item->subtotal, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <table class="totals" style="margin-top:10px; float:right; width:40%">
    <tr><td>Sub-total</td><td class="right">{{ number_format($sale->total, 2) }}</td></tr>
    @if($sale->discount > 0)
    <tr><td>Discount</td><td class="right">-{{ number_format($sale->discount, 2) }}</td></tr>
    @endif
    @if($sale->tax > 0)
    <tr><td>VAT (18%)</td><td class="right">{{ number_format($sale->tax, 2) }}</td></tr>
    @endif
    <tr><td><strong>TOTAL (TZS)</strong></td><td class="right"><strong>{{ number_format($sale->grand_total, 2) }}</strong></td></tr>
    <tr><td>Payment</td><td class="right">{{ strtoupper($sale->payment_method) }}</td></tr>
    @php
      $cashPayment = $sale->payments->first(fn($p) => $p->method === 'cash');
      $tendered = null; $change = null;
      if ($cashPayment?->notes && preg_match('/Tendered: ([\d.]+)/', $cashPayment->notes, $m)) {
          $tendered = (float) $m[1];
          $change   = max(0, $tendered - $sale->grand_total);
      }
    @endphp
    @if($tendered !== null)
    <tr><td>Amount Paid</td><td class="right">{{ number_format($tendered, 2) }}</td></tr>
    <tr><td><strong>Change Given</strong></td><td class="right"><strong>{{ number_format($change, 2) }}</strong></td></tr>
    @endif
  </table>

  <div class="footer" style="clear:both; margin-top:40px">
    Issued by {{ config('app.name') }} | TIN: [tenant TIN] | EFD No: [EFD serial]
  </div>
</body>
</html>
