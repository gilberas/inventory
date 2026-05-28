@extends('layouts.app')
@section('title', 'Brands')
@section('breadcrumb', 'Products / Brands')
@section('topbar-actions')
    <a href="{{ route('brands.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Brand</a>
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Brand Name</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($brands as $brand)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $brand->name }}</strong></td>
                    <td>{{ $brand->products_count }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('brands.edit', $brand) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('brands.destroy', $brand) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4">
                    <div class="empty-state"><i class="fas fa-bookmark"></i><h3>No brands yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $brands->withQueryString()->links() }}</div>
</div>
@endsection
