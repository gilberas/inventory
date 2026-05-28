<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = ProductCategory::withCount('products')
            ->with('parent')
            ->orderBy('name')
            ->paginate(20);

        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        $parents = ProductCategory::whereNull('parent_id')->orderBy('name')->get();
        return view('categories.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255|unique:product_categories',
            'parent_id' => 'nullable|exists:product_categories,id',
        ]);

        ProductCategory::create($request->only('name', 'parent_id', 'description'));

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(ProductCategory $category)
    {
        $parents = ProductCategory::whereNull('parent_id')->where('id', '!=', $category->id)->orderBy('name')->get();
        return view('categories.edit', compact('category', 'parents'));
    }

    public function update(Request $request, ProductCategory $category)
    {
        $request->validate([
            'name'      => 'required|string|max:255|unique:product_categories,name,' . $category->id,
            'parent_id' => 'nullable|exists:product_categories,id',
        ]);

        $category->update($request->only('name', 'parent_id', 'description'));

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(ProductCategory $category)
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', 'Cannot delete category with products.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
