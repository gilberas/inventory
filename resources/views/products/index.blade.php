@extends('layouts.app')
@section('title', 'Products')
@section('header', 'Products')

@section('header-actions')
    <a href="{{ route('products.create') }}" class="btn-primary">
        <i class="fas fa-plus mr-1"></i> Add Product
    </a>
@endsection

@section('content')
<!-- Filters -->
<div class="bg-white rounded-xl shadow-sm p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="label">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or SKU…" class="input">
        </div>
        <div>
            <label class="label">Category</label>
            <select name="category_id" class="input">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Brand</label>
            <select name="brand_id" class="input">
                <option value="">All Brands</option>
                @foreach($brands as $brand)
                    <option value="{{ $brand->id }}" @selected(request('brand_id') == $brand->id)>{{ $brand->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Status</label>
            <select name="status" class="input">
                <option value="">All</option>
                <option value="active"   @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <button class="btn-secondary">Filter</button>
        <a href="{{ route('products.index') }}" class="btn-ghost">Reset</a>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-xl shadow-sm overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
            <tr>
                <th class="px-5 py-3 text-left">SKU</th>
                <th class="px-5 py-3 text-left">Product</th>
                <th class="px-5 py-3 text-left">Category</th>
                <th class="px-5 py-3 text-right">Stock</th>
                <th class="px-5 py-3 text-right">Cost</th>
                <th class="px-5 py-3 text-right">Price</th>
                <th class="px-5 py-3 text-center">Status</th>
                <th class="px-5 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
            @forelse($products as $product)
            <tr class="hover:bg-gray-50 {{ $product->isLowStock() ? 'bg-red-50' : '' }}">
                <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ $product->sku }}</td>
                <td class="px-5 py-3">
                    <p class="font-medium text-gray-900">{{ $product->name }}</p>
                    <p class="text-xs text-gray-400">{{ $product->brand->name ?? '—' }}</p>
                </td>
                <td class="px-5 py-3 text-gray-600">{{ $product->category->name ?? '—' }}</td>
                <td class="px-5 py-3 text-right">
                    <span class="{{ $product->isLowStock() ? 'text-red-600 font-bold' : 'text-gray-800' }}">
                        {{ $product->totalStock() }} {{ $product->unit->abbreviation ?? '' }}
                    </span>
                    @if($product->isLowStock())
                        <span class="ml-1 text-xs text-red-500"><i class="fas fa-exclamation-triangle"></i></span>
                    @endif
                </td>
                <td class="px-5 py-3 text-right text-gray-600">{{ number_format($product->cost_price, 2) }}</td>
                <td class="px-5 py-3 text-right text-gray-800 font-medium">{{ number_format($product->selling_price, 2) }}</td>
                <td class="px-5 py-3 text-center">
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $product->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-5 py-3 text-right space-x-2">
                    <a href="{{ route('products.show', $product) }}"   class="text-blue-500 hover:text-blue-700"><i class="fas fa-eye"></i></a>
                    <a href="{{ route('products.edit', $product) }}"   class="text-yellow-500 hover:text-yellow-700"><i class="fas fa-edit"></i></a>
                    <form method="POST" action="{{ route('products.destroy', $product) }}" class="inline" onsubmit="return confirm('Delete this product?')">
                        @csrf @method('DELETE')
                        <button class="text-red-500 hover:text-red-700"><i class="fas fa-trash"></i></button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="8" class="px-5 py-10 text-center text-gray-400">No products found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-5 py-4 border-t border-gray-100">
        {{ $products->links() }}
    </div>
</div>
@endsection
