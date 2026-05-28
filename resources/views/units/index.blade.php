@extends('layouts.app')
@section('title', 'Units of Measure')
@section('breadcrumb', 'Products / Units')
@section('topbar-actions')
    <a href="{{ route('units.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Unit</a>
@endsection
@section('content')
<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Unit Name</th><th>Abbreviation</th><th>Products</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($units as $unit)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><strong>{{ $unit->name }}</strong></td>
                    <td><span class="badge badge-sky">{{ $unit->abbreviation }}</span></td>
                    <td>{{ $unit->products_count }}</td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('units.edit', $unit) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Delete?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5">
                    <div class="empty-state"><i class="fas fa-ruler"></i><h3>No units yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $units->withQueryString()->links() }}</div>
</div>
@endsection
