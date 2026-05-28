@extends('layouts.app')
@section('title', $product->name)
@section('header', $product->name)

@section('header-actions')
    <a href="{{ route('products.edit', $product) }}" class="btn-secondary"><i class="fas fa-edit mr-1"></i> Edit</a>
@endsection

@section('content')
<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-5">
        <!-- Info -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div><p class="text-gray-400">SKU</p><p class="font-mono font-medium">{{ $product->sku }}</p></div>
                <div><p class="text-gray-400">Unit</p><p>{{ $product->unit->name ?? '—' }}</p></div>
                <div><p class="text-gray-400">Category</p><p>{{ $product->category->name ?? '—' }}</p></div>
                <div><p class="text-gray-400">Brand</p><p>{{ $product->brand->name ?? '—' }}</p></div>
                <div><p class="text-gray-400">Cost Price</p><p class="font-semibold">{{ number_format($product->cost_price, 2) }}</p></div>
                <div><p class="text-gray-400">Selling Price</p><p class="font-semibold text-green-700">{{ number_format($product->selling_price, 2) }}</p></div>
                <div><p class="text-gray-400">Minimum Stock</p><p>{{ $product->minimum_stock }}</p></div>
                <div><p class="text-gray-400">Status</p>
                    <span class="px-2 py-0.5 rounded-full text-xs {{ $product->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $product->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
            @if($product->description)
            <div class="mt-4 pt-4 border-t border-gray-100">
                <p class="text-gray-400 text-sm mb-1">Description</p>
                <p class="text-gray-700 text-sm">{{ $product->description }}</p>
            </div>
            @endif
        </div>

        <!-- Stock by warehouse -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Stock by Warehouse</h3>
                @if($product->isLowStock())
                    <span class="px-3 py-1 bg-red-100 text-red-700 text-xs rounded-full font-medium">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Low Stock
                    </span>
                @endif
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-5 py-3 text-left">Warehouse</th>
                        <th class="px-5 py-3 text-right">Qty</th>
                        <th class="px-5 py-3 text-right">Reserved</th>
                        <th class="px-5 py-3 text-right">Available</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($product->stockBalances as $balance)
                    <tr>
                        <td class="px-5 py-3">{{ $balance->warehouse->name }}</td>
                        <td class="px-5 py-3 text-right">{{ $balance->quantity }}</td>
                        <td class="px-5 py-3 text-right text-yellow-600">{{ $balance->reserved_quantity }}</td>
                        <td class="px-5 py-3 text-right font-medium {{ $balance->available_quantity < $product->minimum_stock ? 'text-red-600' : 'text-green-700' }}">
                            {{ $balance->available_quantity }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No stock recorded yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 font-medium text-sm">
                    <tr>
                        <td class="px-5 py-3">Total</td>
                        <td class="px-5 py-3 text-right">{{ $product->stockBalances->sum('quantity') }}</td>
                        <td class="px-5 py-3 text-right">{{ $product->stockBalances->sum('reserved_quantity') }}</td>
                        <td class="px-5 py-3 text-right">{{ $product->stockBalances->sum('available_quantity') }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Images sidebar -->
    <div class="space-y-5">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h3 class="font-semibold text-gray-800 mb-3">Images</h3>
            @forelse($product->images->sortByDesc('is_primary') as $img)
            <div class="mb-2">
                <img src="{{ Storage::url($img->path) }}" alt="{{ $product->name }}"
                     class="w-full rounded-lg object-cover h-40 {{ $img->is_primary ? 'ring-2 ring-blue-400' : '' }}">
                @if($img->is_primary) <p class="text-xs text-blue-500 mt-1 text-center">Primary</p> @endif
            </div>
            @empty
            <div class="h-32 bg-gray-100 rounded-lg flex items-center justify-center text-gray-400">
                <i class="fas fa-image text-3xl"></i>
            </div>
            @endforelse
        </div>

        <div class="bg-white rounded-xl shadow-sm p-5 text-sm space-y-2">
            <h3 class="font-semibold text-gray-800 mb-3">Tracking</h3>
            <p class="{{ $product->track_batch  ? 'text-green-700' : 'text-gray-400' }}">
                <i class="fas {{ $product->track_batch  ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i> Batch Tracking
            </p>
            <p class="{{ $product->track_expiry ? 'text-green-700' : 'text-gray-400' }}">
                <i class="fas {{ $product->track_expiry ? 'fa-check-circle' : 'fa-times-circle' }} mr-1"></i> Expiry Tracking
            </p>
        </div>
    </div>
</div>
@endsection
