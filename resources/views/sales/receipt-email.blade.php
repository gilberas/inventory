<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:sans-serif;font-size:14px;color:#333">
  <h2>Receipt #{{ $sale->receipt_no }}</h2>
  <p>Date: {{ $sale->created_at->format('d F Y H:i') }}</p>
  @if($sale->customer)
  <p>Dear {{ $sale->customer->name }},<br>Thank you for your purchase.</p>
  @endif
  <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
    <tr style="background:#f5f5f5">
      <th style="text-align:left;border:1px solid #ddd">Item</th>
      <th style="text-align:right;border:1px solid #ddd">Qty</th>
      <th style="text-align:right;border:1px solid #ddd">Price</th>
      <th style="text-align:right;border:1px solid #ddd">Subtotal</th>
    </tr>
    @foreach($sale->items as $item)
    <tr>
      <td style="border:1px solid #ddd">{{ $item->product->name }}</td>
      <td style="text-align:right;border:1px solid #ddd">{{ number_format($item->qty, 2) }}</td>
      <td style="text-align:right;border:1px solid #ddd">{{ number_format($item->unit_price, 2) }}</td>
      <td style="text-align:right;border:1px solid #ddd">{{ number_format($item->subtotal, 2) }}</td>
    </tr>
    @endforeach
    <tr>
      <td colspan="3" style="text-align:right"><strong>Total (TZS)</strong></td>
      <td style="text-align:right;border:1px solid #ddd"><strong>{{ number_format($sale->grand_total, 2) }}</strong></td>
    </tr>
  </table>
  <p style="margin-top:20px">Payment method: {{ strtoupper($sale->payment_method) }}</p>
  <p>{{ config('app.name') }}</p>
</body>
</html>
