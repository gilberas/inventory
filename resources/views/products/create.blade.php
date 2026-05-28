@extends('layouts.app')
@section('title', 'Add Product')
@section('header', 'Add Product')

@section('content')
<div class="max-w-3xl">
<form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-700 border-b pb-2">Basic Information</h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
                <label class="label">Product Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" class="input" required>
            </div>
            <div>
                <label class="label">SKU (auto-generated if empty)</label>
                <input type="text" name="sku" value="{{ old('sku') }}" class="input" placeholder="PRD-00001">
            </div>
            <div>
                <label class="label">Unit *</label>
                <select name="unit_id" class="input" required>
                    <option value="">Select unit…</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected(old('unit_id') == $unit->id)>{{ $unit->name }} ({{ $unit->abbreviation }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Category *</label>
                <select name="category_id" class="input" required>
                    <option value="">Select category…</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Brand</label>
                <select name="brand_id" class="input">
                    <option value="">None</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-span-2">
                <label class="label">Description</label>
                <textarea name="description" rows="3" class="input">{{ old('description') }}</textarea>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 space-y-4">
        <h3 class="font-semibold text-gray-700 border-b pb-2">Pricing & Stock</h3>
        <div class="grid grid-cols-3 gap-4">
            <div>
                <label class="label">Cost Price *</label>
                <input type="number" name="cost_price" value="{{ old('cost_price', 0) }}" step="0.01" min="0" class="input" required>
            </div>
            <div>
                <label class="label">Selling Price *</label>
                <input type="number" name="selling_price" value="{{ old('selling_price', 0) }}" step="0.01" min="0" class="input" required>
            </div>
            <div>
                <label class="label">Minimum Stock *</label>
                <input type="number" name="minimum_stock" value="{{ old('minimum_stock', 0) }}" step="0.01" min="0" class="input" required>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6 space-y-3">
        <h3 class="font-semibold text-gray-700 border-b pb-2">Tracking Options</h3>
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="track_batch"  value="1" @checked(old('track_batch'))  class="w-4 h-4 rounded text-blue-600">
            <span class="text-sm text-gray-700">Track by batch/lot number</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="track_expiry" value="1" @checked(old('track_expiry')) class="w-4 h-4 rounded text-blue-600">
            <span class="text-sm text-gray-700">Track expiry date</span>
        </label>
        <label class="flex items-center gap-3 cursor-pointer">
            <input type="checkbox" name="is_active"    value="1" @checked(old('is_active', true)) class="w-4 h-4 rounded text-blue-600">
            <span class="text-sm text-gray-700">Active (visible in orders)</span>
        </label>
    </div>

    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="font-semibold text-gray-700 border-b pb-2 mb-3">Images</h3>
        <input type="file" name="images[]" multiple accept="image/*" class="text-sm text-gray-600">
        <p class="text-xs text-gray-400 mt-1">First image will be the primary image. Max 2MB each.</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary">Save Product</button>
        <a href="{{ route('products.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>
</div>
@endsection
