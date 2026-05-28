@extends('layouts.app')
@section('title', 'Categories')
@section('breadcrumb', 'Products / Categories')
@section('topbar-actions')
    <a href="{{ route('categories.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Category</a>
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Parent</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $category->parent?->name ?? '—' }}</td>
                    <td>{{ $category->products_count }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('categories.edit', $category) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5">
                    <div class="empty-state"><i class="fas fa-tags"></i><h3>No categories yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $categories->withQueryString()->links() }}</div>
</div>
@endsection
