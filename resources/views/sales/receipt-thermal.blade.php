<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: monospace; font-size: 12px; width: 80mm; margin: 0; padding: 4mm; }
  .center { text-align: center; }
  .right { text-align: right; }
  hr { border: none; border-top: 1px dashed #000; }
  table { width: 100%; border-collapse: collapse; }
  td { padding: 1px 0; }
</style>
</head>
<body>
  <div class="center">
    <strong>{{ config('app.name') }}</strong><br>
    Receipt #{{ $sale->receipt_no }}<br>
    {{ $sale->created_at->format('d/m/Y H:i') }}
  </div>
  <hr>
  @if($sale->customer)
  <div>Customer: {{ $sale->customer->name }}</div>
  @endif
  <div>Cashier: {{ $sale->cashier->name }}</div>
  <hr>
  <table>
    @foreach($sale->items as $item)
    <tr>
      <td>{{ $item->product->name }}</td>
      <td class="right">{{ number_format($item->qty, 2) }} × {{ number_format($item->unit_price, 2) }}</td>
    </tr>
    <tr>
      <td></td>
      <td class="right">{{ number_format($item->subtotal, 2) }}</td>
    </tr>
    @endforeach
  </table>
  <hr>
  <table>
    @if($sale->discount > 0)
    <tr><td>Discount</td><td class="right">-{{ number_format($sale->discount, 2) }}</td></tr>
    @endif
    @if($sale->tax > 0)
    <tr><td>Tax (VAT)</td><td class="right">{{ number_format($sale->tax, 2) }}</td></tr>
    @endif
    <tr><td><strong>TOTAL</strong></td><td class="right"><strong>TZS {{ number_format($sale->grand_total, 2) }}</strong></td></tr>
  </table>
  <hr>
  <div>Payment: {{ strtoupper($sale->payment_method) }}</div>
  @php
    $cashPayment = $sale->payments->first(fn($p) => $p->method === 'cash');
    $tendered = null; $change = null;
    if ($cashPayment?->notes && preg_match('/Tendered: ([\d.]+)/', $cashPayment->notes, $m)) {
        $tendered = (float) $m[1];
        $change   = max(0, $tendered - $sale->grand_total);
    }
  @endphp
  @if($tendered !== null)
  <table>
    <tr><td>Amount Paid</td><td class="right">TZS {{ number_format($tendered, 2) }}</td></tr>
    <tr><td><strong>Change Given</strong></td><td class="right"><strong>TZS {{ number_format($change, 2) }}</strong></td></tr>
  </table>
  @endif
  <hr>
  <div class="center">Thank you for your business!</div>
</body>
</html>
