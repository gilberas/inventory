@extends('layouts.app')
@section('title', 'Expenses')
@section('breadcrumb', 'Finance / Expenses')
@section('topbar-actions')
    @can('expenses.manage')
    <a href="#" onclick="document.getElementById('newExpenseModal').style.display='flex'" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> New Expense</a>
    @endcan
@endsection
@section('content')

{{-- Filter bar --}}
<div class="card" style="margin-bottom:1rem">
    <form method="GET" style="display:flex;gap:.75rem;flex-wrap:wrap;align-items:flex-end;padding:.25rem 0">
        <div>
            <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:.2rem">Category</label>
            <select name="category" class="form-control" style="min-width:140px">
                <option value="">All categories</option>
                @foreach(['rent','utilities','salaries','transport','maintenance','marketing','office','other'] as $cat)
                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:.2rem">Status</label>
            <select name="status" class="form-control" style="min-width:140px">
                <option value="">All statuses</option>
                <option value="draft"            {{ request('status') === 'draft'            ? 'selected' : '' }}>Draft</option>
                <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>Pending Approval</option>
                <option value="approved"         {{ request('status') === 'approved'         ? 'selected' : '' }}>Approved</option>
                <option value="rejected"         {{ request('status') === 'rejected'         ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:.2rem">From</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control">
        </div>
        <div>
            <label style="font-size:.75rem;color:var(--muted);display:block;margin-bottom:.2rem">To</label>
            <input type="date" name="to" value="{{ request('to') }}" class="form-control">
        </div>
        <button type="submit" class="btn btn-secondary btn-sm" style="align-self:flex-end"><i class="fas fa-filter"></i> Filter</button>
        @if(request()->hasAny(['category','status','from','to']))
        <a href="{{ route('expenses.index') }}" class="btn btn-secondary btn-sm" style="align-self:flex-end"><i class="fas fa-xmark"></i> Clear</a>
        @endif
    </form>
</div>

<div class="card">
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount (TZS)</th>
                    <th>Date</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($expenses as $expense)
                <tr>
                    <td>{{ $expenses->firstItem() + $loop->index }}</td>
                    <td><span class="badge badge-blue">{{ ucfirst($expense->category) }}</span></td>
                    <td style="color:var(--muted)">{{ \Illuminate\Support\Str::limit($expense->description, 45) }}</td>
                    <td><strong>{{ number_format($expense->amount, 2) }}</strong></td>
                    <td style="color:var(--muted)">{{ \Carbon\Carbon::parse($expense->expense_date)->format('d M Y') }}</td>
                    <td style="color:var(--muted)">{{ $expense->createdBy?->name ?? '—' }}</td>
                    <td>
                        @php
                            $statusMap = [
                                'draft'            => ['badge-amber',  'Draft'],
                                'pending_approval' => ['badge-amber',  'Pending'],
                                'approved'         => ['badge-green',  'Approved'],
                                'rejected'         => ['badge-red',    'Rejected'],
                            ];
                            [$cls, $label] = $statusMap[$expense->status] ?? ['badge-amber', ucfirst($expense->status)];
                        @endphp
                        <span class="badge {{ $cls }}">{{ $label }}</span>
                    </td>
                    <td>
                        <div style="display:flex;gap:.35rem">
                            <a href="{{ route('expenses.show', $expense) }}" class="btn btn-secondary btn-sm btn-icon"><i class="fas fa-eye"></i></a>
                            @can('expenses.manage')
                            @if($expense->status === 'pending_approval')
                            <form method="POST" action="{{ route('expenses.approve', $expense) }}" onsubmit="return confirm('Approve this expense?')">
                                @csrf
                                <button class="btn btn-primary btn-sm btn-icon" title="Approve"><i class="fas fa-check"></i></button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8">
                    <div class="empty-state">
                        <i class="fas fa-money-bill-wave"></i>
                        <h3>No expenses found</h3>
                        <p>Record business expenses to track spending and manage approvals.</p>
                    </div>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper">{{ $expenses->withQueryString()->links() }}</div>
</div>
@endsection
