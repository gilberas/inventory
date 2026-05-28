<?php

namespace App\Http\Controllers;

use App\Models\{Brand, Product, ProductCategory, Unit, Warehouse};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'brand', 'unit', 'stockBalances']);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('sku', 'like', "%{$request->search}%");
            });
        }
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->brand_id)    $query->where('brand_id', $request->brand_id);
        if ($request->status)      $query->where('is_active', $request->status === 'active');

        $products   = $query->latest()->paginate(15)->withQueryString();
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();

        return view('products.index', compact('products', 'categories', 'brands'));
    }

    public function create()
    {
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();
        $units      = Unit::all();
        return view('products.create', compact('categories', 'brands', 'units'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category_id'   => 'required|exists:product_categories,id',
            'brand_id'      => 'nullable|exists:brands,id',
            'unit_id'       => 'required|exists:units,id',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'track_expiry'  => 'boolean',
            'track_batch'   => 'boolean',
            'description'   => 'nullable|string',
            'sku'           => 'nullable|string|unique:products,sku',
            'images.*'      => 'nullable|image|max:2048',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $product = Product::create($validated);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $i => $image) {
                    $path = $image->store('products', 'public');
                    $product->images()->create(['path' => $path, 'is_primary' => $i === 0, 'sort_order' => $i]);
                }
            }
        });

        return redirect()->route('products.index')->with('success', 'Product created successfully.');
    }

    public function show(Product $product)
    {
        $product->load(['category', 'brand', 'unit', 'images', 'stockBalances.warehouse', 'batches']);
        $warehouses = Warehouse::active()->get();
        return view('products.show', compact('product', 'warehouses'));
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::active()->get();
        $brands     = Brand::active()->get();
        $units      = Unit::all();
        $product->load('images');
        return view('products.edit', compact('product', 'categories', 'brands', 'units'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'category_id'   => 'required|exists:product_categories,id',
            'brand_id'      => 'nullable|exists:brands,id',
            'unit_id'       => 'required|exists:units,id',
            'cost_price'    => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock' => 'required|numeric|min:0',
            'description'   => 'nullable|string',
            'sku'           => 'nullable|string|unique:products,sku,' . $product->id,
        ]);

        $product->update($validated);
        return redirect()->route('products.index')->with('success', 'Product updated successfully.');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }
}
