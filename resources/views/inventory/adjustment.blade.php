@extends('layouts.app')
@section('title', 'Stock Adjustment')
@section('breadcrumb', 'Inventory / Adjustments')
@section('content')
<div class="card">
    <div class="card-header">
        <h2 class="card-title">New Stock Adjustment</h2>
    </div>
    <form method="POST" action="{{ route('inventory.adjustment.store') }}" id="adjustForm">
        @csrf
        <div class="form-grid" style="margin-bottom:1.5rem">
            <div class="form-group">
                <label>Warehouse *</label>
                <select name="warehouse_id" required>
                    <option value="">Select warehouse...</option>
                    @foreach($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label>Date</label>
                <input type="date" name="date" value="{{ date('Y-m-d') }}">
            </div>
            <div class="form-group full">
                <label>Notes</label>
                <input type="text" name="notes" placeholder="Reason for adjustment...">
            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <h3 style="font-size:.9rem;font-weight:700">Adjustment Items</h3>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addRow()"><i class="fas fa-plus"></i> Add Product</button>
        </div>

        <div class="table-wrapper">
            <table id="itemsTable">
                <thead>
                    <tr><th>Product</th><th>Current Qty</th><th>Adjustment (+/-)</th><th>Reason</th><th></th></tr>
                </thead>
                <tbody id="itemsBody">
                    <tr id="row-0">
                        <td>
                            <select name="items[0][product_id]" required style="min-width:200px" onchange="updateCurrent(this,0)">
                                <option value="">Select product...</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-sku="{{ $product->sku }}">{{ $product->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><span id="current-0" style="color:var(--muted)">—</span></td>
                        <td><input type="number" name="items[0][quantity]" step="0.01" placeholder="e.g. -5 or +10" required style="width:120px"></td>
                        <td><input type="text" name="items[0][reason]" placeholder="Damage, count error..." required style="min-width:160px"></td>
                        <td><button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeRow(0)"><i class="fas fa-times"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Adjustment</button>
            <a href="{{ route('inventory.index') }}" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>
@push('scripts')
<script>
let rowCount = 1;
const products = @json($products->map(fn($p) => ['id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku]));

function addRow() {
    const i = rowCount++;
    const options = products.map(p => `<option value="${p.id}">${p.name} (${p.sku})</option>`).join('');
    const tr = document.createElement('tr');
    tr.id = `row-${i}`;
    tr.innerHTML = `
        <td><select name="items[${i}][product_id]" required style="min-width:200px" onchange="updateCurrent(this,${i})">
            <option value="">Select product...</option>${options}</select></td>
        <td><span id="current-${i}" style="color:var(--muted)">—</span></td>
        <td><input type="number" name="items[${i}][quantity]" step="0.01" placeholder="e.g. -5" required style="width:120px"></td>
        <td><input type="text" name="items[${i}][reason]" placeholder="Reason..." required style="min-width:160px"></td>
        <td><button type="button" class="btn btn-danger btn-sm btn-icon" onclick="removeRow(${i})"><i class="fas fa-times"></i></button></td>`;
    document.getElementById('itemsBody').appendChild(tr);
}
function removeRow(i) {
    const row = document.getElementById(`row-${i}`);
    if (row) row.remove();
}
function updateCurrent(sel, i) {
    // Could fetch current stock via AJAX here
    document.getElementById(`current-${i}`).textContent = '—';
}
</script>
@endpush
@endsection
