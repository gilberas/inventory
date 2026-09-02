<?php

namespace App\Http\Controllers;

use App\Jobs\ImportProductsJob;
use App\Models\ActivityLog;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductImage;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Writer\XLSX\Writer as XLSXWriter;

class ProductController extends Controller
{
    // ── Listing & Detail ──────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'primaryImage'])
            ->withCount(['stockBalances']);

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('sku',     'like', "%{$term}%")
                  ->orWhere('barcode', 'like', "%{$term}%");
            });
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->filled('brand_id'))    $query->where('brand_id',    $request->brand_id);
        if ($request->filled('status'))      $query->where('status',      $request->status);

        $products   = $query->latest()->paginate(15)->withQueryString();
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();

        return view('products.index', compact('products', 'categories', 'brands'));
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'supplier', 'unit', 'images', 'stockBalances.warehouse', 'batches']);
        $warehouses = Warehouse::active()->get();
        return view('products.show', compact('product', 'warehouses'));
    }

    // ── AJAX instant search (for POS / quick-lookup) ──────────────────────────

    public function search(Request $request)
    {
        $term = $request->input('q', '');

        $products = Product::active()
            ->with('primaryImage')
            ->where(function ($q) use ($term) {
                $q->where('name',    'like', "%{$term}%")
                  ->orWhere('sku',     'like', "%{$term}%")
                  ->orWhere('barcode', '=',    $term);
            })
            ->limit(20)
            ->get(['id', 'name', 'sku', 'barcode', 'selling_price', 'tax_rate', 'status']);

        return response()->json($products);
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create()
    {
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();
        $units      = Unit::all();
        $suppliers  = Supplier::where('is_active', true)->get();

        return view('products.create', compact('categories', 'brands', 'units', 'suppliers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'category_id'     => 'required|exists:product_categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
            'supplier_id'     => 'nullable|exists:suppliers,id',
            'unit_id'         => 'nullable|exists:units,id',
            'cost_price'      => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'minimum_stock'   => 'nullable|numeric|min:0',
            'reorder_level'   => 'nullable|integer|min:0',
            'unit_of_measure' => 'nullable|string|max:50',
            'expiry_date'     => 'nullable|date',
            'status'          => 'nullable|in:active,inactive',
            'track_expiry'    => 'boolean',
            'track_batch'     => 'boolean',
            'description'     => 'nullable|string',
            'sku'             => 'nullable|string|max:100',
            'barcode'         => 'nullable|string|max:100',
            'images'          => 'nullable|array|max:5',
            'images.*'        => 'image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        // Business rule: products must go to leaf categories only
        $this->assertLeafCategory($validated['category_id']);

        $product = DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

            if ($request->hasFile('images')) {
                $count = $product->images()->count();
                foreach ($request->file('images') as $i => $image) {
                    if ($count >= 5) break;
                    $path = $image->store('products', 'public');
                    $product->images()->create([
                        'path'       => $path,
                        'is_primary' => ($count + $i) === 0,
                        'sort_order' => $count + $i,
                    ]);
                    $count++;
                }
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Product::class,
                'model_id'   => $product->id,
                'new_values' => $product->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $product;
        });

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Product $product)
    {
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();
        $units      = Unit::all();
        $suppliers  = Supplier::where('is_active', true)->get();
        $product->load('images');

        return view('products.edit', compact('product', 'categories', 'brands', 'units', 'suppliers'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'category_id'     => 'required|exists:product_categories,id',
            'brand_id'        => 'nullable|exists:brands,id',
            'supplier_id'     => 'nullable|exists:suppliers,id',
            'unit_id'         => 'nullable|exists:units,id',
            'cost_price'      => 'required|numeric|min:0',
            'selling_price'   => 'required|numeric|min:0',
            'tax_rate'        => 'nullable|numeric|min:0|max:100',
            'minimum_stock'   => 'nullable|numeric|min:0',
            'reorder_level'   => 'nullable|integer|min:0',
            'unit_of_measure' => 'nullable|string|max:50',
            'expiry_date'     => 'nullable|date',
            'status'          => 'nullable|in:active,inactive',
            'description'     => 'nullable|string',
            'sku'             => "nullable|string|max:100|unique:products,sku,{$product->id}",
            'barcode'         => "nullable|string|max:100|unique:products,barcode,{$product->id}",
        ]);

        // Business rule: products must go to leaf categories only
        $this->assertLeafCategory($validated['category_id']);

        $old = $product->toArray();

        DB::transaction(function () use ($product, $validated, $request, $old) {
            $product->update($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Product::class,
                'model_id'   => $product->id,
                'old_values' => $old,
                'new_values' => $product->fresh()->toArray(),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, Product $product)
    {
        // Block delete if active purchase order lines reference this product
        $activePOLines = DB::table('purchase_order_items as poi')
            ->join('purchase_orders as po', 'po.id', '=', 'poi.purchase_order_id')
            ->where('poi.product_id', $product->id)
            ->whereIn('po.status', ['DRAFT', 'PENDING_APPROVAL', 'APPROVED'])
            ->whereRaw('poi.quantity_received < poi.quantity_ordered')
            ->count();

        if ($activePOLines > 0) {
            return back()->withErrors(['product' => 'Cannot delete: product has active purchase order lines.']);
        }

        DB::transaction(function () use ($product, $request) {
            $old = $product->toArray();
            $product->delete();

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'model_type' => Product::class,
                'model_id'   => $product->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }

    // ── Images ────────────────────────────────────────────────────────────────

    public function uploadImage(Request $request, Product $product)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($product->images()->count() >= 5) {
            return back()->withErrors(['image' => 'Maximum 5 images allowed per product.']);
        }

        $path     = $request->file('image')->store('products', 'public');
        $isPrimary = $product->images()->count() === 0;
        $sortOrder = $product->images()->max('sort_order') + 1;

        $product->images()->create([
            'path'       => $path,
            'is_primary' => $isPrimary,
            'sort_order' => $sortOrder,
        ]);

        return back()->with('success', 'Image uploaded.');
    }

    public function deleteImage(Request $request, Product $product, ProductImage $image)
    {
        abort_if($image->product_id !== $product->id, 404);

        Storage::disk('public')->delete($image->path);
        $wasPrimary = $image->is_primary;
        $image->delete();

        // Promote the first remaining image to primary
        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Image removed.');
    }

    // ── Import ────────────────────────────────────────────────────────────────

    public function importTemplate()
    {
        $headers = ['name', 'sku', 'barcode', 'category_name', 'brand_name', 'cost_price',
                    'selling_price', 'tax_rate', 'reorder_level', 'unit_of_measure', 'status'];

        $example = ['Example Product', 'SKU-ELEC-ABCD', '1234567890', 'Electronics',
                    'Samsung', '80.00', '120.00', '18', '10', 'unit', 'active'];

        $csv = implode(',', $headers) . "\n" . implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="product_import_template.csv"',
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $tmpPath   = $file->getRealPath();

        $rowCount = $this->countRows($tmpPath, $extension);

        if ($rowCount > 100) {
            $stored = $file->store('imports');
            ImportProductsJob::dispatch(
                auth()->user()->tenant_id,
                auth()->id(),
                $stored,
            );

            return back()->with('info', "Large import ({$rowCount} rows) queued. You will be notified when complete.");
        }

        // Inline processing for ≤100 rows
        $job    = new ImportProductsJob(auth()->user()->tenant_id, auth()->id(), '');
        $result = $job->processFile($tmpPath);

        $msg = "Import complete: {$result['created']} created, {$result['updated']} updated.";

        if (!empty($result['errors'])) {
            return back()
                ->with('warning', $msg . ' Some rows had errors.')
                ->with('import_errors', $result['errors']);
        }

        return back()->with('success', $msg);
    }

    private function countRows(string $path, string $extension): int
    {
        if ($extension === 'xlsx' || $extension === 'xls') {
            if (!class_exists(XLSXReader::class)) return 0;
            $reader = new XLSXReader();
            $reader->open($path);
            $count = -1; // subtract header
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $_) { $count++; }
                break;
            }
            $reader->close();
            return max(0, $count);
        }

        $handle = fopen($path, 'r');
        $count  = -1; // subtract header
        while (fgetcsv($handle) !== false) { $count++; }
        fclose($handle);
        return max(0, $count);
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');

        $products = Product::with(['category', 'brand', 'supplier'])
            ->get(['id', 'sku', 'barcode', 'name', 'category_id', 'brand_id', 'supplier_id',
                   'cost_price', 'selling_price', 'tax_rate', 'minimum_stock', 'reorder_level',
                   'unit_of_measure', 'expiry_date', 'status']);

        $headers = ['name', 'sku', 'barcode', 'category_name', 'brand_name', 'supplier_name',
                    'cost_price', 'selling_price', 'tax_rate', 'reorder_level', 'unit_of_measure',
                    'expiry_date', 'status'];

        $rows = $products->map(fn ($p) => [
            $p->name,
            $p->sku,
            $p->barcode,
            $p->category?->name,
            $p->brand?->name,
            $p->supplier?->name,
            $p->cost_price,
            $p->selling_price,
            $p->tax_rate,
            $p->reorder_level,
            $p->unit_of_measure,
            $p->expiry_date?->toDateString(),
            $p->status,
        ]);

        if ($format === 'excel') {
            return $this->exportExcel($headers, $rows);
        }

        // Default: CSV stream
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'products.csv', ['Content-Type' => 'text/csv']);
    }

    // ── Business rule helpers ─────────────────────────────────────────────────

    private function assertLeafCategory(int $categoryId): void
    {
        $category = Category::withoutGlobalScopes()->find($categoryId);
        if ($category && !$category->isLeaf()) {
            throw ValidationException::withMessages([
                'category_id' => ['Products can only be assigned to leaf categories (categories with no sub-categories).'],
            ]);
        }
    }

    private function exportExcel(array $headers, $rows)
    {
        if (!class_exists(XLSXWriter::class)) {
            return response()->json(['message' => 'Excel export requires openspout/openspout.'], 422);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'products_export') . '.xlsx';
        $writer = new XLSXWriter();
        $writer->openToFile($tmp);
        $writer->addRow(Row::fromValues($headers));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(fn ($v) => $v ?? '', $row)));
        }
        $writer->close();

        return response()->download($tmp, 'products.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
