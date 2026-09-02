<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @php
        $dims = [
            'small'  => ['w' => '38mm', 'h' => '25mm', 'font' => '7pt'],
            'medium' => ['w' => '50mm', 'h' => '30mm', 'font' => '8pt'],
            'large'  => ['w' => '90mm', 'h' => '38mm', 'font' => '9pt'],
        ];
        $d = $dims[$size ?? 'medium'];
    @endphp
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: Arial, sans-serif; }
    .label {
        display: inline-block;
        width: {{ $d['w'] }};
        height: {{ $d['h'] }};
        border: 0.5pt solid #ccc;
        padding: 1mm;
        vertical-align: top;
        overflow: hidden;
        page-break-inside: avoid;
    }
    .label-barcode { text-align: center; }
    .label-barcode img { max-width: 100%; max-height: 60%; display: block; margin: 0 auto; }
    .label-name {
        font-size: {{ $d['font'] }};
        font-weight: bold;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        text-align: center;
        margin-top: 0.5mm;
    }
    .label-meta {
        font-size: {{ $d['font'] }};
        color: #555;
        text-align: center;
    }
</style>
</head>
<body>
@foreach($products as $product)
<div class="label">
    <div class="label-barcode">
        <img src="data:image/svg+xml;base64,{{ $barcodes[$product->id] ?? '' }}" alt="{{ $product->barcode ?? $product->sku }}">
    </div>
    <div class="label-name">{{ $product->name }}</div>
    <div class="label-meta">
        {{ $product->sku }} &bull; TZS {{ number_format($product->selling_price, 0) }}
    </div>
</div>
@endforeach
</body>
</html>
