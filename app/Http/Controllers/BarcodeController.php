<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateBarcodeSheetJob;
use App\Models\ActivityLog;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeController extends Controller
{
    // GET /products/{product}/barcode?type=code128|ean13|qr
    public function show(Request $request, Product $product): Response
    {
        $type     = $request->query('type', 'code128');
        $tenantId = auth()->user()->tenant_id;
        $cacheKey = "tenant:{$tenantId}:barcode:{$product->id}:{$type}";

        $svg = Cache::remember($cacheKey, 86400, fn () => $this->generateSvg($product, $type));

        return response($svg, 200)->header('Content-Type', 'image/svg+xml');
    }

    // POST /barcodes/bulk-print
    public function bulkPrint(Request $request): mixed
    {
        $request->validate([
            'product_ids'   => 'required|array|min:1',
            'product_ids.*' => 'integer',
            'size'          => 'in:small,medium,large',
        ]);

        $productIds = $request->input('product_ids');
        $size       = $request->input('size', 'medium');

        if (count($productIds) > 50) {
            GenerateBarcodeSheetJob::dispatch($productIds, $size, auth()->id())->onQueue('default');
            return response()->json([
                'queued'  => true,
                'message' => 'Label sheet is being generated. You will be notified when ready.',
            ]);
        }

        $products = Product::whereIn('id', $productIds)->get();
        $generator = new BarcodeGeneratorSVG();

        $barcodes = [];
        foreach ($products as $product) {
            $code              = $this->barcodeCode($product);
            $barcodes[$product->id] = base64_encode($generator->getBarcode($code, $generator::TYPE_CODE_128));
        }

        $pdf = Pdf::loadView('barcodes.label', compact('products', 'barcodes', 'size'));

        return $pdf->download('labels-' . date('Ymd') . '.pdf');
    }

    // POST /products/{product}/barcode/assign
    public function assign(Request $request, Product $product): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['barcode' => 'required|string|max:64']);

        $barcode = $request->input('barcode');

        $duplicate = Product::where('barcode', $barcode)
            ->where('id', '!=', $product->id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors(['barcode' => 'This barcode is already assigned to another product.']);
        }

        $product->update(['barcode' => $barcode]);

        $tenantId = auth()->user()->tenant_id;
        foreach (['code128', 'ean13', 'qr'] as $type) {
            Cache::forget("tenant:{$tenantId}:barcode:{$product->id}:{$type}");
        }

        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'barcode_assigned',
            'model_type' => Product::class,
            'model_id'   => $product->id,
            'new_values' => ['barcode' => $barcode],
        ]);

        return back()->with('success', 'Barcode assigned successfully.');
    }

    // GET /pos/scan/{barcode}
    public function posScan(string $barcode): JsonResponse
    {
        $product = Product::where('barcode', $barcode)->orWhere('sku', $barcode)->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product->only(['id', 'name', 'sku', 'barcode', 'selling_price', 'cost_price']));
    }

    // GET /grn/scan/{barcode}
    public function grnScan(string $barcode): JsonResponse
    {
        $product = Product::where('barcode', $barcode)->orWhere('sku', $barcode)->first();

        if (! $product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        return response()->json($product->only(['id', 'name', 'sku', 'barcode', 'cost_price', 'selling_price']));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function generateSvg(Product $product, string $type): string
    {
        if ($type === 'qr') {
            $content = "SKU:{$product->sku}|Name:{$product->name}|Price:{$product->selling_price}";
            return (new SvgWriter())->write(new QrCode($content))->getString();
        }

        $generator = new BarcodeGeneratorSVG();
        $code      = $this->barcodeCode($product);

        if ($type === 'ean13') {
            $digits = preg_replace('/\D/', '', $code);
            $digits = substr(str_pad($digits, 12, '0', STR_PAD_LEFT), -12);
            return $generator->getBarcode($digits, $generator::TYPE_EAN_13);
        }

        return $generator->getBarcode($code, $generator::TYPE_CODE_128);
    }

    private function barcodeCode(Product $product): string
    {
        return $product->barcode ?: $product->sku;
    }
}
