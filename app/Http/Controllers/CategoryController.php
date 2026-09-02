<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    // ── Index (flat paginated list + roots/children eager-loaded) ─────────────

    public function index()
    {
        $categories = Category::with('parent')
            ->withCount('products')
            ->orderByRaw('COALESCE(parent_id, id), parent_id IS NOT NULL, name')
            ->paginate(30);

        return view('categories.index', compact('categories'));
    }

    // ── Show (JSON — child_count, product_count, total_stock_value) ───────────

    public function show(Category $category)
    {
        return response()->json([
            'id'               => $category->id,
            'name'             => $category->name,
            'slug'             => $category->slug,
            'depth'            => $category->depth,
            'parent'           => $category->parent?->only('id', 'name'),
            'child_count'      => $category->children()->count(),
            'product_count'    => $category->products()->count(),
            'total_stock_value' => $category->stock_value,
            'image_path'       => $category->image_path,
            'description'      => $category->description,
        ]);
    }

    // ── Create / Store ────────────────────────────────────────────────────────

    public function create()
    {
        // Only roots and children (depth 0/1) can be parents
        $parents = Category::roots()
            ->with('children')
            ->orderBy('name')
            ->get();

        return view('categories.form', compact('parents'));
    }

    public function store(Request $request)
    {
        $tenantId  = auth()->user()->tenant_id;

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Depth guard — reject if the chosen parent is itself a grandchild
        if (!empty($validated['parent_id'])) {
            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->depth >= 2) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Maximum category depth (3 levels) reached. Cannot add children to a grandchild category.'],
                ]);
            }
        }

        $category = DB::transaction(function () use ($validated, $request) {
            $category = Category::create($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Category::class,
                'model_id'   => $category->id,
                'new_values' => $category->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $category;
        });

        return redirect()->route('categories.index')->with('success', "Category '{$category->name}' created.");
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(Category $category)
    {
        $parents = Category::roots()
            ->where('id', '!=', $category->id)
            ->with('children')
            ->orderBy('name')
            ->get();

        return view('categories.form', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'parent_id'   => ['nullable', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        // Depth guard — same as store
        if (!empty($validated['parent_id'])) {
            // Cannot set self as parent
            if ($validated['parent_id'] == $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => ['A category cannot be its own parent.'],
                ]);
            }

            $parent = Category::findOrFail($validated['parent_id']);
            if ($parent->depth >= 2) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Maximum category depth (3 levels) reached.'],
                ]);
            }
        }

        $old = $category->toArray();

        DB::transaction(function () use ($category, $validated, $request, $old) {
            $category->update($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Category::class,
                'model_id'   => $category->id,
                'old_values' => $old,
                'new_values' => $category->fresh()->toArray(),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(Request $request, Category $category)
    {
        if ($category->products()->count() > 0) {
            return back()->withErrors(['category' => "Cannot delete '{$category->name}': it has products assigned."]);
        }

        if ($category->children()->count() > 0) {
            return back()->withErrors(['category' => "Cannot delete '{$category->name}': it has sub-categories."]);
        }

        DB::transaction(function () use ($category, $request) {
            $old = $category->toArray();
            $category->delete();

            // Delete the stored image if any
            if ($old['image_path']) {
                Storage::disk('public')->delete($old['image_path']);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'model_type' => Category::class,
                'model_id'   => $category->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }

    // ── Image Upload ──────────────────────────────────────────────────────────

    public function uploadImage(Request $request, Category $category)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Remove old image
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }

        $path = $request->file('image')->store('categories', 'public');
        $category->update(['image_path' => $path]);

        return back()->with('success', 'Category image updated.');
    }
}
