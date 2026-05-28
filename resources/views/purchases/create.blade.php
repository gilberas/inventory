@extends('layouts.app')
@section('title', 'New Purchase Order')
@section('header', 'New Purchase Order')

@section('content')
<form method="POST" action="{{ route('purchases.store') }}" id="po-form" class="space-y-6 max-w-5xl">
    @csrf
    <div class="bg-white rounded-xl shadow-sm p-6 grid grid-cols-2 gap-4">
        <div>
            <label class="label">Supplier *</label>
            <select name="supplier_id" class="input" required>
                <option value="">Select supplier…</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}" @selected(old('supplier_id') == $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Warehouse *</label>
            <select name="warehouse_id" class="input" required>
                @foreach($warehouses as $w)
                    <option value="{{ $w->id }}" @selected($w->is_default)>{{ $w->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Order Date *</label>
            <input type="date" name="order_date" value="{{ old('order_date', date('Y-m-d')) }}" class="input" required>
        </div>
        <div>
            <label class="label">Expected Delivery</label>
            <input type="date" name="expected_date" value="{{ old('expected_date') }}" class="input">
        </div>
        <div class="col-span-2">
            <label class="label">Notes</label>
            <textarea name="notes" rows="2" class="input">{{ old('notes') }}</textarea>
        </div>
    </div>

    <!-- Line Items -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Order Items</h3>
            <button type="button" id="add-item" class="btn-secondary text-xs"><i class="fas fa-plus mr-1"></i> Add Item</button>
        </div>
        <table class="w-full text-sm" id="items-table">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Product</th>
                    <th class="px-4 py-3 text-right w-28">Qty</th>
                    <th class="px-4 py-3 text-right w-32">Unit Cost</th>
                    <th class="px-4 py-3 text-right w-32">Subtotal</th>
                    <th class="px-4 py-3 w-10"></th>
                </tr>
            </thead>
            <tbody id="items-body">
                <tr id="empty-row" class="text-center text-gray-400">
                    <td colspan="5" class="py-8">Click "Add Item" to add products</td>
                </tr>
            </tbody>
            <tfoot class="bg-gray-50 font-medium">
                <tr>
                    <td colspan="3" class="px-4 py-3 text-right text-gray-600">Total</td>
                    <td class="px-4 py-3 text-right text-gray-900" id="grand-total">0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary">Create Purchase Order</button>
        <a href="{{ route('purchases.index') }}" class="btn-ghost">Cancel</a>
    </div>
</form>

@push('scripts')
<script>
const products = @json($products->map(fn($p) => ['id'=>$p->id,'name'=>$p->name,'sku'=>$p->sku,'cost'=>$p->cost_price]));
let itemIndex = 0;

document.getElementById('add-item').addEventListener('click', () => {
    document.getElementById('empty-row').style.display = 'none';
    const tbody = document.getElementById('items-body');
    const i = itemIndex++;
    const row = document.createElement('tr');
    row.className = 'border-t border-gray-100';
    row.innerHTML = `
        <td class="px-4 py-2">
            <select name="items[${i}][product_id]" class="input product-select" required>
                <option value="">Select product…</option>
                ${products.map(p => `<option value="${p.id}" data-cost="${p.cost}">${p.name} (${p.sku})</option>`).join('')}
            </select>
        </td>
        <td class="px-4 py-2"><input type="number" name="items[${i}][quantity_ordered]" class="input text-right qty-input" min="0.01" step="0.01" value="1" required></td>
        <td class="px-4 py-2"><input type="number" name="items[${i}][unit_cost]" class="input text-right cost-input" min="0" step="0.01" value="0.00" required></td>
        <td class="px-4 py-2 text-right subtotal font-medium">0.00</td>
        <td class="px-4 py-2 text-center"><button type="button" class="text-red-400 hover:text-red-600 remove-row"><i class="fas fa-times"></i></button></td>
    `;
    tbody.appendChild(row);
    row.querySelector('.product-select').addEventListener('change', e => {
        const opt = e.target.selectedOptions[0];
        row.querySelector('.cost-input').value = opt.dataset.cost || 0;
        updateRow(row);
    });
    row.querySelectorAll('.qty-input, .cost-input').forEach(el => el.addEventListener('input', () => updateRow(row)));
    row.querySelector('.remove-row').addEventListener('click', () => { row.remove(); updateTotal(); });
});

function updateRow(row) {
    const qty  = parseFloat(row.querySelector('.qty-input').value)  || 0;
    const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
    row.querySelector('.subtotal').textContent = (qty * cost).toFixed(2);
    updateTotal();
}

function updateTotal() {
    const total = [...document.querySelectorAll('.subtotal')].reduce((s, el) => s + parseFloat(el.textContent), 0);
    document.getElementById('grand-total').textContent = total.toFixed(2);
}
</script>
@endpush
@endsection
