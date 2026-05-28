@extends('layouts.app')
@section('title', 'Suppliers')
@section('breadcrumb', 'Purchasing / Suppliers')
@section('topbar-actions')
    <a href="{{ route('suppliers.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Supplier</a>
@endsection
@section('content')
<div class="card">
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <div class="search-input">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search suppliers..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Contact</th><th>Phone</th><th>Email</th><th>POs</th><th>Balance</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($suppliers as $supplier)
                <tr>
                    <td>{{ $suppliers->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $supplier->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $supplier->contact_person ?? '—' }}</td>
                    <td style="color:var(--muted)">{{ $supplier->phone ?? '—' }}</td>
                    <td style="color:var(--muted)">{{ $supplier->email ?? '—' }}</td>
                    <td>{{ $supplier->purchase_orders_count ?? 0 }}</td>
                    <td>
                        @php $bal = $supplier->balance ?? 0; @endphp
                        <span class="{{ $bal > 0 ? 'badge badge-amber' : 'badge badge-green' }}">
                            {{ number_format($bal, 2) }}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}" onsubmit="return confirm('Delete supplier?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8">
                    <div class="empty-state"><i class="fas fa-industry"></i><h3>No suppliers yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $suppliers->withQueryString()->links() }}</div>
</div>
@endsection
