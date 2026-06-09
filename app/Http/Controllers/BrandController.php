<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index()
    {
        $brands = Brand::withCount('products')->orderBy('name')->paginate(20);
        return view('brands.index', compact('brands'));
    }

    public function create()
    {
        return view('brands.form');
    }

    public function store(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('brands')->where('tenant_id', $tenantId),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        $brand = DB::transaction(function () use ($request) {
            $brand = Brand::create($request->only('name', 'description'));

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Brand::class,
                'model_id'   => $brand->id,
                'new_values' => $brand->toArray(),
                'ip_address' => $request->ip(),
            ]);

            return $brand;
        });

        return redirect()->route('brands.index')->with('success', "Brand '{$brand->name}' created.");
    }

    public function edit(Brand $brand)
    {
        return view('brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand)
    {
        $tenantId = auth()->user()->tenant_id;

        $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('brands')->where('tenant_id', $tenantId)->ignore($brand->id),
            ],
            'description' => 'nullable|string|max:1000',
        ]);

        $old = $brand->toArray();

        DB::transaction(function () use ($brand, $request, $old) {
            $brand->update($request->only('name', 'description'));

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Brand::class,
                'model_id'   => $brand->id,
                'old_values' => $old,
                'new_values' => $brand->fresh()->toArray(),
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('brands.index')->with('success', 'Brand updated.');
    }

    public function destroy(Request $request, Brand $brand)
    {
        if ($brand->products()->count() > 0) {
            return back()->withErrors(['brand' => "Cannot delete '{$brand->name}': it has products assigned."]);
        }

        DB::transaction(function () use ($brand, $request) {
            $old = $brand->toArray();
            $brand->delete();

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'model_type' => Brand::class,
                'model_id'   => $brand->id,
                'old_values' => $old,
                'new_values' => null,
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('brands.index')->with('success', 'Brand deleted.');
    }
}
