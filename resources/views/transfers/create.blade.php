@extends('layouts.app')
@section('title', 'New Branch Transfer')
@section('breadcrumb', 'Inventory / Transfers / New')

@section('content')
<div class="card">
    <div class="card-header">
        <h2 class="card-title"><i class="fas fa-truck-fast" style="color:var(--primary)"></i> New Branch Transfer</h2>
    </div>

    <form method="POST" action="{{ route('transfers.store') }}">
        @csrf

        {{-- Header --}}
        <div class="form-grid" style="margin-bottom:1.5rem">
            <div class="form-group">
                <label>From Branch *</label>
                <select name="from_branch_id" id="fromBranch" required>
                    <option value="">Select source branch...</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('from_branch_id') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>To Branch *</label>
                <select name="to_branch_id" required>
                    <option value="">Select destination branch...</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" {{ old('to_branch_id') == $branch->id ? 'selected' : '' }}>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <textarea name="notes" placeholder="Reason for transfer...">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Items --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h3 style="font-size:.9rem;font-weight:700;color:var(--text)">
                <i class="fas fa-boxes-stacked" style="color:var(--primary)"></i> Products to Transfer
            </h3>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>

        <div class="table-wrapper">
            <table id="itemsTable">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Available Stock</th>
                        <th>Qty Requested</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="itemsBody">
                    <tr id="row-0">
                        <td>
                            <select name="items[0][product_id]" required style="min-width:220px"
                                    onchange="fetchStock(this, 0)">
                                <option value="">Select product...</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <span id="stock-0" style="color:var(--muted);font-size:.85rem">—</span>
                        </td>
                        <td>
                            <input type="number" name="items[0][qty_requested]"
                                   min="0.01" step="0.01" placeholder="Qty" required style="width:110px">
                        </td>
                        <td>
                            <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeRow(0)">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        @error('items')
            <p style="color:var(--danger);font-size:.85rem;margin-top:.5rem">{{ $message }}</p>
        @enderror

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-floppy-disk"></i> Create Transfer
            </button>
            <a href="{{ route('transfers.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
let rowCount = 1;
const products = @json($products->map(fn($p) => ['id' => $p->id, 'name' => $p->name, 'sku' => $p->sku]));

function addRow() {
    const i = rowCount++;
    const opts = products.map(p =>
        `<option value="${p.id}">${p.name} (${p.sku})</option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.id = `row-${i}`;
    tr.innerHTML = `
        <td>
            <select name="items[${i}][product_id]" required style="min-width:220px"
                    onchange="fetchStock(this, ${i})">
                <option value="">Select product...</option>${opts}
            </select>
        </td>
        <td><span id="stock-${i}" style="color:var(--muted);font-size:.85rem">—</span></td>
        <td>
            <input type="number" name="items[${i}][qty_requested]"
                   min="0.01" step="0.01" placeholder="Qty" required style="width:110px">
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeRow(${i})">
                <i class="fas fa-times"></i>
            </button>
        </td>`;
    document.getElementById('itemsBody').appendChild(tr);
}

function removeRow(i) {
    document.getElementById(`row-${i}`)?.remove();
}

function fetchStock(sel, i) {
    const productId = sel.value;
    const branchId  = document.getElementById('fromBranch').value;
    const stockEl   = document.getElementById(`stock-${i}`);

    if (!productId || !branchId) { stockEl.textContent = '—'; return; }

    fetch(`/inventory/stock-level?product_id=${productId}&branch_id=${branchId}`)
        .then(r => r.json())
        .then(data => {
            stockEl.textContent = (data.quantity_available ?? '—') + ' available';
            stockEl.style.color = data.quantity_available > 0 ? 'var(--accent)' : 'var(--danger)';
        })
        .catch(() => { stockEl.textContent = '—'; });
}
</script>
@endpush
@endsection
