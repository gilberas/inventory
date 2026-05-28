@extends('layouts.app')
@section('title', 'Customers')
@section('breadcrumb', 'Sales / Customers')
@section('topbar-actions')
    <a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Customer</a>
@endsection
@section('content')
<div class="card">
    <div class="search-bar">
        <form method="GET" style="display:contents">
            <div class="search-input">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="search" placeholder="Search customers..." value="{{ request('search') }}">
            </div>
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Phone</th><th>Email</th><th>Total Orders</th><th>Balance</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                <tr>
                    <td>{{ $customers->firstItem() + $loop->index }}</td>
                    <td><strong>{{ $customer->name }}</strong></td>
                    <td style="color:var(--muted)">{{ $customer->phone ?? '—' }}</td>
                    <td style="color:var(--muted)">{{ $customer->email ?? '—' }}</td>
                    <td>{{ $customer->sales_orders_count ?? 0 }}</td>
                    <td>
                        @php $bal = $customer->balance ?? 0; @endphp
                        <span class="{{ $bal > 0 ? 'badge badge-sky' : 'badge badge-green' }}">
                            {{ number_format($bal, 2) }}
                        </span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('customers.show', $customer) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-eye"></i></a>
                            <a href="{{ route('customers.edit', $customer) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-pen"></i></a>
                            <form method="POST" action="{{ route('customers.destroy', $customer) }}" onsubmit="return confirm('Delete customer?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-danger btn-sm btn-icon"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7">
                    <div class="empty-state"><i class="fas fa-users"></i><h3>No customers yet</h3></div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $customers->withQueryString()->links() }}</div>
</div>
@endsection
