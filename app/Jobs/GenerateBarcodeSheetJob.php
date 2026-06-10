<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Picqer\Barcode\BarcodeGeneratorSVG;

class GenerateBarcodeSheetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly array $productIds,
        private readonly string $size,
        private readonly int $userId,
    ) {}

    public function handle(): void
    {
        $products  = Product::withoutGlobalScopes()->whereIn('id', $this->productIds)->get();
        $generator = new BarcodeGeneratorSVG();

        $barcodes = [];
        foreach ($products as $product) {
            $code = $product->barcode ?: $product->sku;
            $barcodes[$product->id] = base64_encode($generator->getBarcode($code, $generator::TYPE_CODE_128));
        }

        $pdf  = Pdf::loadView('barcodes.label', [
            'products' => $products,
            'barcodes' => $barcodes,
            'size'     => $this->size,
        ]);
        $path = 'barcode-sheets/labels-' . $this->userId . '-' . time() . '.pdf';

        Storage::put($path, $pdf->output());
    }
}
