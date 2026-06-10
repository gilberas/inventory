@extends('layouts.app')

@section('title', 'Quick Transfer')

@section('content')
<div class="min-h-screen bg-gray-50" style="max-width:480px;margin:0 auto;">

    {{-- Header --}}
    <div class="bg-white border-b px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
        <a href="{{ route('transfers.index') }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-lg font-semibold text-gray-900">Quick Transfer</h1>
    </div>

    @if ($errors->any())
    <div class="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
        <ul class="list-disc pl-4 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Step indicator --}}
    <div class="flex items-center px-4 py-4 gap-2">
        <div class="flex-1 text-center" id="step-indicator-1">
            <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center mx-auto text-sm font-bold">1</div>
            <p class="text-xs text-blue-600 mt-1">Select</p>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200" id="line-1-2"></div>
        <div class="flex-1 text-center" id="step-indicator-2">
            <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center mx-auto text-sm font-bold" id="step2-circle">2</div>
            <p class="text-xs text-gray-400 mt-1" id="step2-label">Review</p>
        </div>
        <div class="flex-1 h-0.5 bg-gray-200" id="line-2-3"></div>
        <div class="flex-1 text-center" id="step-indicator-3">
            <div class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center mx-auto text-sm font-bold" id="step3-circle">3</div>
            <p class="text-xs text-gray-400 mt-1" id="step3-label">Submit</p>
        </div>
    </div>

    {{-- ── STEP 1: Product + Destination + Qty ─────────────────────────────── --}}
    <div id="step-1" class="mx-4 space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Product</label>
            <select id="product-select"
                    class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    onchange="updateProductInfo()">
                <option value="">— Select product —</option>
                @foreach($products as $product)
                <option value="{{ $product->id }}"
                        data-unit="{{ $product->unit?->abbreviation ?? 'units' }}"
                        data-sku="{{ $product->sku }}">
                    {{ $product->name }} ({{ $product->sku }})
                </option>
                @endforeach
            </select>
            <div id="product-info" class="hidden mt-2 text-xs text-gray-500 bg-gray-50 rounded-lg px-3 py-2">
                SKU: <span id="product-sku">—</span> · Unit: <span id="product-unit">—</span>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Destination Branch</label>
            <select id="destination-select"
                    class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">— Select destination —</option>
                @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Quantity</label>
            <div class="flex items-center gap-3">
                <button type="button" onclick="adjustQty(-1)"
                        class="w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 text-xl font-bold text-gray-700 transition">−</button>
                <input type="number" id="qty-input" value="1" min="0.01" step="0.01"
                       class="flex-1 border border-gray-300 rounded-xl px-3 py-3 text-center text-lg font-semibold focus:ring-2 focus:ring-blue-500">
                <button type="button" onclick="adjustQty(1)"
                        class="w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 text-xl font-bold text-gray-700 transition">+</button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (optional)</label>
            <textarea id="notes-input" rows="2" maxlength="1000"
                      class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                      placeholder="Optional notes..."></textarea>
        </div>

        <button type="button" onclick="goToReview()"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-md transition text-base">
            Review Transfer →
        </button>
    </div>

    {{-- ── STEP 2: Review ───────────────────────────────────────────────────── --}}
    <div id="step-2" class="mx-4 hidden space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-4">Transfer Summary</h2>

            <div class="space-y-3">
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">From</span>
                    <span class="text-sm font-medium text-gray-900">{{ auth()->user()->branch?->name ?? 'Your Branch' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-sm text-gray-500">To</span>
                    <span class="text-sm font-medium text-gray-900" id="review-destination">—</span>
                </div>
                <div class="border-t pt-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-500">Product</span>
                        <span class="text-sm font-medium text-gray-900" id="review-product">—</span>
                    </div>
                    <div class="flex justify-between mt-2">
                        <span class="text-sm text-gray-500">Quantity</span>
                        <span class="text-lg font-bold text-blue-600" id="review-qty">—</span>
                    </div>
                </div>
                <div id="review-notes-row" class="border-t pt-3 hidden">
                    <span class="text-xs text-gray-400">Notes</span>
                    <p class="text-sm text-gray-700 mt-1" id="review-notes"></p>
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button type="button" onclick="goToStep(1)"
                    class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-4 rounded-2xl transition text-base">
                ← Edit
            </button>
            <button type="button" onclick="goToStep(3)"
                    class="flex-2 flex-grow bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-md transition text-base">
                Confirm & Submit
            </button>
        </div>
    </div>

    {{-- ── STEP 3: Submit (POST form) ───────────────────────────────────────── --}}
    <div id="step-3" class="mx-4 hidden">
        <form id="transfer-form" method="POST" action="{{ route('transfers.store') }}">
            @csrf
            <input type="hidden" name="to_branch_id"          id="form-to-branch">
            <input type="hidden" name="notes"                  id="form-notes">
            <input type="hidden" name="items[0][product_id]"   id="form-product-id">
            <input type="hidden" name="items[0][qty_requested]" id="form-qty">

            <div class="bg-white rounded-xl shadow-sm p-5 mb-4">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <h2 class="text-center text-lg font-bold text-gray-900 mb-1">Ready to Submit</h2>
                <p class="text-center text-sm text-gray-500">Transfer will be sent for approval.</p>
            </div>

            <button type="submit"
                    class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-5 rounded-2xl shadow-md transition text-lg">
                Submit Transfer Request
            </button>
            <button type="button" onclick="goToStep(2)"
                    class="w-full mt-3 text-sm text-gray-500 hover:text-gray-700 py-2">
                ← Back to Review
            </button>
        </form>
    </div>

    <div class="pb-8"></div>
</div>

<script>
const destinations = @json($branches->pluck('name', 'id'));

function updateProductInfo() {
    const sel = document.getElementById('product-select');
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('product-info');
    if (sel.value) {
        document.getElementById('product-sku').textContent  = opt.dataset.sku;
        document.getElementById('product-unit').textContent = opt.dataset.unit;
        info.classList.remove('hidden');
    } else {
        info.classList.add('hidden');
    }
}

function adjustQty(delta) {
    const input = document.getElementById('qty-input');
    const v = parseFloat(input.value) || 0;
    input.value = Math.max(0.01, v + delta).toFixed(2);
}

function goToStep(n) {
    [1, 2, 3].forEach(i => {
        document.getElementById('step-' + i).classList.toggle('hidden', i !== n);
    });
    [2, 3].forEach(i => {
        const circle = document.getElementById('step' + i + '-circle');
        const label  = document.getElementById('step' + i + '-label');
        if (i <= n) {
            circle.classList.remove('bg-gray-200', 'text-gray-500');
            circle.classList.add('bg-blue-600', 'text-white');
            label.classList.remove('text-gray-400');
            label.classList.add('text-blue-600');
        } else {
            circle.classList.add('bg-gray-200', 'text-gray-500');
            circle.classList.remove('bg-blue-600', 'text-white');
            label.classList.add('text-gray-400');
            label.classList.remove('text-blue-600');
        }
    });
}

function goToReview() {
    const productSel = document.getElementById('product-select');
    const destSel    = document.getElementById('destination-select');
    const qty        = parseFloat(document.getElementById('qty-input').value);

    if (!productSel.value) { alert('Please select a product.'); return; }
    if (!destSel.value)    { alert('Please select a destination branch.'); return; }
    if (!qty || qty <= 0)  { alert('Quantity must be greater than zero.'); return; }

    const productName = productSel.options[productSel.selectedIndex].text;
    const destName    = destinations[destSel.value] || destSel.options[destSel.selectedIndex].text;
    const unit        = productSel.options[productSel.selectedIndex].dataset.unit;
    const notes       = document.getElementById('notes-input').value.trim();

    document.getElementById('review-product').textContent     = productName;
    document.getElementById('review-destination').textContent = destName;
    document.getElementById('review-qty').textContent         = qty + ' ' + unit;

    const notesRow = document.getElementById('review-notes-row');
    if (notes) {
        document.getElementById('review-notes').textContent = notes;
        notesRow.classList.remove('hidden');
    } else {
        notesRow.classList.add('hidden');
    }

    // Pre-fill the hidden form fields for step 3
    document.getElementById('form-to-branch').value  = destSel.value;
    document.getElementById('form-product-id').value = productSel.value;
    document.getElementById('form-qty').value        = qty;
    document.getElementById('form-notes').value      = notes;

    goToStep(2);
}
</script>
@endsection
