@extends('layouts.app')
@section('title', 'POS Terminal')

{{-- Tailwind bundle (POS uses utility classes) + cancel the .content padding --}}
@push('styles')
    @vite(['resources/css/app.css'])
    <style>
        /* POS full-screen: cancel the .content { padding:1.5rem } from app layout */
        .content { padding: 0 !important; overflow: hidden !important; }

        @keyframes scanLine {
            0%   { top: 4px; opacity: 1; }
            50%  { opacity: 0.5; }
            100% { top: calc(100% - 6px); opacity: 1; }
        }
    </style>
@endpush

@section('content')

{{-- ═══ POS TERMINAL — fills viewport below topbar ═══════════════════════════ --}}
<div id="pos-app"
     class="flex flex-col overflow-hidden"
     data-warehouse="{{ $session?->warehouse_id ?? '' }}"
     style="height:calc(100vh - 57px); background:#111827;">

    {{-- ── DARK TOP BAR ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between px-4 py-2 shrink-0"
         style="background:#1f2937; border-bottom:1px solid #374151;">

        <div class="flex items-center gap-3">
            <h1 class="text-white font-bold text-sm tracking-tight">
                <i class="fas fa-cash-register mr-1.5 text-blue-400"></i>POS Terminal
            </h1>
            <div id="session-badge"
                 class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border
                        {{ $session
                            ? 'text-green-400 border-green-500/30'
                            : 'text-red-400 border-red-500/30' }}"
                 style="{{ $session ? 'background:rgba(34,197,94,.15)' : 'background:rgba(239,68,68,.15)' }}">
                <span class="w-1.5 h-1.5 rounded-full {{ $session ? 'bg-green-400 animate-pulse' : 'bg-red-400' }}"></span>
                {{ $session ? 'Session Open' : 'No Session' }}
            </div>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs" style="color:#9ca3af">{{ $user->name }}</span>
            @if(!$session)
            <button onclick="openSessionModal()"
                    class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors">
                Open Session
            </button>
            @else
            <button onclick="closeSession()"
                    class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                    style="background:#374151; color:#d1d5db;">
                Close Session
            </button>
            @endif
        </div>
    </div>

    {{-- ── KEYBOARD HINTS BAR ───────────────────────────────────────────────── --}}
    <div class="flex items-center gap-5 px-4 shrink-0"
         style="background:#161f2e; border-bottom:1px solid #2d3748; padding-top:.35rem; padding-bottom:.35rem; color:#6b7280; font-size:.72rem;">
        @foreach(['F2' => 'Search', 'F4' => 'Discount', 'F9' => 'Charge', 'Esc' => 'Close dropdown'] as $key => $label)
        <span>
            <kbd style="background:#374151;color:#d1d5db;padding:.1rem .4rem;border-radius:3px;font-family:monospace;font-size:.68rem;border:1px solid #4b5563;">{{ $key }}</kbd>
            <span style="margin-left:.25rem;">{{ $label }}</span>
        </span>
        @endforeach
        <span style="margin-left:auto;color:#374151;font-size:.68rem;">SmartStock ERP · POS v1.0</span>
    </div>

    {{-- ── MAIN 2-COLUMN ────────────────────────────────────────────────────── --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ════ LEFT PANEL — Search + Cart (65%) ════════════════════════════ --}}
        <div class="flex flex-col" style="width:65%; border-right:1px solid #e5e7eb; background:#f9fafb;">

            {{-- Search bar + camera button --}}
            <div class="px-4 py-3 shrink-0" style="background:white; border-bottom:1px solid #e5e7eb;">
                <div class="flex gap-2">

                    {{-- Barcode / text search input --}}
                    <div class="relative flex-1">
                        <input type="text"
                               id="barcode-input"
                               autocomplete="off"
                               autofocus
                               placeholder="Scan barcode or type product name… (F2)"
                               class="w-full border-2 border-blue-400 focus:border-blue-600 rounded-xl px-4 py-3 text-base font-mono pr-10"
                               oninput="onBarcodeInput(this.value)"
                               onkeydown="handleBarcodeKey(event)">
                        <div id="barcode-spinner" class="hidden absolute right-3 top-3.5">
                            <div class="w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>

                    {{-- Camera scanner toggle button --}}
                    <button id="camera-scan-btn"
                            onclick="toggleCameraScanner()"
                            title="Scan with camera"
                            class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0 transition-all"
                            style="background:#2563eb; color:white; border:2px solid #1d4ed8;">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </button>
                </div>

                {{-- Camera scanner panel (hidden until toggled) --}}
                <div id="camera-scanner-panel"
                     class="hidden mt-3 rounded-xl overflow-hidden relative"
                     style="height:220px; border:1px solid rgba(59,130,246,.4); background:#000;">

                    <video id="scanner-video" autoplay playsinline muted
                           class="w-full h-full object-cover"></video>

                    {{-- Viewfinder overlay --}}
                    <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                        <div style="position:relative;width:192px;height:128px;">
                            <div style="position:absolute;top:0;left:0;width:24px;height:24px;border-top:2px solid #60a5fa;border-left:2px solid #60a5fa;border-radius:4px 0 0 0;"></div>
                            <div style="position:absolute;top:0;right:0;width:24px;height:24px;border-top:2px solid #60a5fa;border-right:2px solid #60a5fa;border-radius:0 4px 0 0;"></div>
                            <div style="position:absolute;bottom:0;left:0;width:24px;height:24px;border-bottom:2px solid #60a5fa;border-left:2px solid #60a5fa;border-radius:0 0 0 4px;"></div>
                            <div style="position:absolute;bottom:0;right:0;width:24px;height:24px;border-bottom:2px solid #60a5fa;border-right:2px solid #60a5fa;border-radius:0 0 4px 0;"></div>
                            <div id="scan-line" style="position:absolute;left:4px;right:4px;height:2px;background:#60a5fa;border-radius:2px;animation:scanLine 2s linear infinite;"></div>
                        </div>
                        <p style="color:#93c5fd;font-size:.75rem;margin-top:.75rem;font-weight:500;">
                            Point camera at barcode or QR code
                        </p>
                    </div>

                    <button onclick="toggleCameraScanner()"
                            style="position:absolute;top:.5rem;right:.5rem;width:28px;height:28px;background:rgba(0,0,0,.65);color:white;border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.85rem;z-index:10;">
                        ✕
                    </button>

                    <div id="scan-result-flash"
                         class="hidden"
                         style="position:absolute;bottom:.5rem;left:.5rem;right:.5rem;background:rgba(34,197,94,.9);color:white;font-size:.75rem;font-weight:600;padding:.5rem .75rem;border-radius:.5rem;text-align:center;">
                        Scanned!
                    </div>
                </div>

                {{-- Search results dropdown --}}
                <div id="search-results"
                     class="hidden mt-1 bg-white rounded-xl shadow-lg border border-gray-200 max-h-64 overflow-y-auto z-20 relative">
                </div>
            </div>

            {{-- Cart items (scrollable) --}}
            <div class="flex-1 overflow-y-auto p-4">
                <div id="cart-empty"
                     class="flex flex-col items-center justify-center text-gray-400 py-8">
                    <svg class="w-10 h-10 mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184
                                 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2
                                 2 0 014 0z"/>
                    </svg>
                    <p class="text-lg font-medium">Cart is empty</p>
                    <p class="text-sm mt-1">Scan a barcode, use the camera, or search above</p>
                </div>
                <div id="cart-items" class="space-y-2 hidden"></div>
            </div>

            {{-- Cart footer --}}
            <div class="px-4 py-2 shrink-0 flex items-center justify-between"
                 style="background:white; border-top:1px solid #e5e7eb; font-size:.75rem;">
                <span id="cart-item-count" style="color:#6b7280;">0 items in cart</span>
                <button onclick="clearCartConfirm()"
                        style="color:#ef4444; background:none; border:none; cursor:pointer; font-size:.75rem;">
                    Clear cart (Esc)
                </button>
            </div>
        </div>

        {{-- ════ RIGHT PANEL — Summary + Payment (35%) ════════════════════════ --}}
        <div class="flex flex-col bg-white" style="width:35%;">

            {{-- Customer selector --}}
            <div class="px-4 py-3 shrink-0" style="border-bottom:1px solid #e5e7eb;">
                <select id="customer-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">Walk-in customer</option>
                </select>
            </div>

            {{-- Warehouse selector --}}
            <div class="px-4 py-2 shrink-0" style="border-bottom:1px solid #e5e7eb;">
                <select id="warehouse-select"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                        onchange="document.getElementById('pos-app').dataset.warehouse = this.value">
                    <option value="">Select warehouse...</option>
                    @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}"
                            {{ ($session?->warehouse_id == $wh->id) ? 'selected' : '' }}>
                        {{ $wh->name }}
                    </option>
                    @endforeach
                </select>
            </div>

            {{-- Order totals (scrollable area) --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">

                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Sub-total</span>
                    <span id="summary-subtotal" class="font-medium">0.00</span>
                </div>

                {{-- Discount row — hidden by default, toggled via F4 --}}
                <div id="discount-row" class="hidden flex items-center gap-2">
                    <label class="text-sm text-gray-500 whitespace-nowrap">Discount</label>
                    <input type="number" id="discount-input" value="0" min="0" step="0.01"
                           class="flex-1 border border-gray-300 rounded-lg px-2 py-1 text-sm text-right"
                           oninput="recalcTotals()">
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Tax</span>
                    <span id="summary-tax" class="font-medium">0.00</span>
                </div>

                <div class="border-t pt-3 flex justify-between">
                    <span class="text-base font-bold text-gray-900">TOTAL</span>
                    <span id="summary-total" class="text-xl font-bold text-blue-700">0.00</span>
                </div>

                {{-- Cash tendered — shown when cash payment method is selected --}}
                <div id="tendered-row" class="hidden space-y-2">
                    <label class="text-sm text-gray-500">Cash Tendered</label>
                    <input type="number" id="tendered-input" min="0" step="0.01"
                           placeholder="Enter amount..."
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-right font-mono text-lg"
                           oninput="recalcChange()">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Change</span>
                        <span id="change-display" class="font-bold text-green-600">0.00</span>
                    </div>
                </div>
            </div>

            {{-- Payment method + Charge button — always pinned to bottom --}}
            <div class="px-4 pb-4 pt-2 shrink-0" style="border-top:1px solid #e5e7eb;">
                <div class="grid grid-cols-3 gap-1 mb-3">
                    @foreach(['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'credit' => 'Credit'] as $method => $label)
                    <button type="button"
                            onclick="selectPaymentMethod('{{ $method }}')"
                            data-method="{{ $method }}"
                            class="pay-method-btn py-2 rounded-lg text-sm font-medium border transition
                                   {{ $method === 'cash'
                                      ? 'bg-blue-600 text-white border-blue-600'
                                      : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>

                {{-- M-Pesa phone input --}}
                <div id="mpesa-phone-row" class="hidden mb-3">
                    <input type="tel" id="mpesa-phone" placeholder="0712345678"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>

                <button id="charge-btn" onclick="charge()"
                        class="w-full bg-green-600 hover:bg-green-700 active:bg-green-800
                               text-white font-bold py-4 rounded-2xl shadow-md transition text-lg
                               disabled:opacity-50 disabled:cursor-not-allowed">
                    Charge (F9)
                </button>
            </div>
        </div>

    </div>{{-- end 2-column --}}

</div>{{-- end #pos-app --}}

{{-- ════ OPEN SESSION MODAL ═══════════════════════════════════════════════════ --}}
<div id="session-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-80">
        <h2 class="text-lg font-bold mb-4">Open POS Session</h2>
        <select id="session-warehouse" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm mb-3">
            @foreach($warehouses as $wh)
            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
            @endforeach
        </select>
        <div class="mb-4">
            <label class="block text-xs text-gray-500 mb-1">Opening Cash (TZS)</label>
            <input type="number" id="opening-cash" value="0" min="0"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-right text-lg font-bold">
        </div>
        <button onclick="submitOpenSession()"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl">
            Open Session
        </button>
        <button onclick="document.getElementById('session-modal').classList.add('hidden')"
                class="w-full mt-2 text-gray-500 hover:text-gray-700 py-2 text-sm">
            Cancel
        </button>
    </div>
</div>

{{-- ════ SALE COMPLETE MODAL ═══════════════════════════════════════════════════ --}}
<div id="complete-modal" class="fixed inset-0 z-50 hidden bg-black/50 flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl p-6 w-80 text-center">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h2 class="text-lg font-bold mb-1">Sale Complete</h2>
        <p class="text-sm text-gray-500 mb-2" id="complete-total"></p>
        <p class="text-2xl font-bold text-green-600 mb-4" id="complete-change"></p>
        <div class="flex gap-2">
            <a id="receipt-link" href="#" target="_blank"
               class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 rounded-xl text-sm">
                Print Receipt
            </a>
            <button onclick="newSale()"
                    class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 rounded-xl text-sm">
                New Sale
            </button>
        </div>
    </div>
</div>

<script>
// ─────────────────────────────────────────────────────────────────────────────
// CORE POS JAVASCRIPT — preserved verbatim from original
// ─────────────────────────────────────────────────────────────────────────────
const CSRF = '{{ csrf_token() }}';
const SEARCH_URL        = '{{ route('pos.products.search') }}';
const SALE_URL          = '{{ route('pos.sales.store') }}';
const SESSION_OPEN_URL  = '{{ route('pos.session.open') }}';
const SESSION_CLOSE_URL = '{{ route('pos.session.close') }}';

let cart = [];          // [{id, name, sku, unit_price, cost_price, qty, unit, stock}]
let paymentMethod = 'cash';
let searchDebounce;

// ── Keyboard shortcuts ──────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
    if (e.key === 'F2') { e.preventDefault(); document.getElementById('barcode-input').focus(); }
    if (e.key === 'F4') { e.preventDefault(); toggleDiscount(); }
    if (e.key === 'F9') { e.preventDefault(); charge(); }
    if (e.key === 'Escape') { closeSearchResults(); }
});

// ── Barcode / search input ──────────────────────────────────────────────────
function onBarcodeInput(val) {
    clearTimeout(searchDebounce);
    if (!val.trim()) { closeSearchResults(); return; }
    searchDebounce = setTimeout(() => doSearch(val.trim()), 200);
}

function handleBarcodeKey(e) {
    if (e.key === 'Enter') {
        const val = e.target.value.trim();
        if (val) { clearTimeout(searchDebounce); doSearch(val); }
    }
}

async function doSearch(q) {
    const warehouseId = document.getElementById('pos-app').dataset.warehouse;
    const spinner     = document.getElementById('barcode-spinner');
    spinner.classList.remove('hidden');

    try {
        const url = SEARCH_URL + '?q=' + encodeURIComponent(q) + (warehouseId ? '&warehouse_id=' + warehouseId : '');
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const products = await res.json();

        if (products.length === 1 && (products[0].barcode === q || products[0].sku === q)) {
            addToCart(products[0]);
            document.getElementById('barcode-input').value = '';
            closeSearchResults();
        } else if (products.length > 0) {
            renderSearchResults(products);
        } else {
            renderSearchResults(null);
        }
    } finally {
        spinner.classList.add('hidden');
    }
}

function renderSearchResults(products) {
    const container = document.getElementById('search-results');
    container.innerHTML = '';
    container.classList.remove('hidden');

    if (!products) {
        container.innerHTML = '<div class="px-4 py-3 text-sm text-gray-500">No products found.</div>';
        return;
    }

    products.forEach(p => {
        const div = document.createElement('div');
        div.className = 'px-4 py-3 hover:bg-blue-50 cursor-pointer flex justify-between items-center border-b last:border-b-0';
        div.innerHTML = `
            <div>
                <p class="font-medium text-sm">${p.name}</p>
                <p class="text-xs text-gray-500">${p.sku} ${p.stock !== undefined ? '· Stock: ' + p.stock : ''}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-blue-600">${formatNum(p.selling_price)}</p>
            </div>`;
        div.addEventListener('click', () => {
            addToCart(p);
            document.getElementById('barcode-input').value = '';
            closeSearchResults();
        });
        container.appendChild(div);
    });
}

function closeSearchResults() {
    document.getElementById('search-results').classList.add('hidden');
}

// ── Cart ────────────────────────────────────────────────────────────────────
function addToCart(product) {
    const existing = cart.find(i => i.id === product.id);
    if (existing) {
        existing.qty++;
    } else {
        cart.push({
            id:         product.id,
            name:       product.name,
            sku:        product.sku,
            unit_price: parseFloat(product.selling_price) || 0,
            cost_price: parseFloat(product.cost_price)    || 0,
            qty:        1,
            unit:       product.unit || 'pcs',
            stock:      product.stock !== undefined ? product.stock : null,
        });
    }
    renderCart();
}

function updateQty(productId, delta) {
    const item = cart.find(i => i.id === productId);
    if (!item) return;
    item.qty = Math.max(0.01, parseFloat(item.qty) + delta);
    if (item.qty <= 0) { cart = cart.filter(i => i.id !== productId); }
    renderCart();
}

function removeFromCart(productId) {
    cart = cart.filter(i => i.id !== productId);
    renderCart();
}

function renderCart() {
    const container = document.getElementById('cart-items');
    const empty     = document.getElementById('cart-empty');

    if (cart.length === 0) {
        container.classList.add('hidden');
        empty.classList.remove('hidden');
        recalcTotals();
        return;
    }

    empty.classList.add('hidden');
    container.classList.remove('hidden');
    container.innerHTML = '';

    cart.forEach(item => {
        const div = document.createElement('div');
        div.className = 'bg-white rounded-xl p-3 flex items-center gap-3 shadow-sm';
        div.innerHTML = `
            <div class="flex-1 min-w-0">
                <p class="font-medium text-sm truncate">${item.name}</p>
                <p class="text-xs text-gray-500">${item.sku} · ${formatNum(item.unit_price)} /${item.unit}</p>
                ${item.stock !== null ? '<p class="text-xs text-gray-400">Stock: ' + item.stock + '</p>' : ''}
            </div>
            <div class="flex items-center gap-2">
                <button onclick="updateQty(${item.id}, -1)"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-sm font-bold">−</button>
                <span class="w-10 text-center font-semibold">${item.qty}</span>
                <button onclick="updateQty(${item.id}, 1)"
                        class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-sm font-bold">+</button>
            </div>
            <div class="text-right w-20">
                <p class="font-bold text-sm">${formatNum(item.unit_price * item.qty)}</p>
                <button onclick="removeFromCart(${item.id})" class="text-xs text-red-400 hover:text-red-600">Remove</button>
            </div>`;
        container.appendChild(div);
    });

    recalcTotals();
}

function recalcTotals() {
    const subtotal = cart.reduce((s, i) => s + i.unit_price * i.qty, 0);
    const discount = parseFloat(document.getElementById('discount-input').value) || 0;
    const taxRate  = 0;
    const tax      = subtotal * taxRate;
    const total    = Math.max(0, subtotal - discount + tax);

    document.getElementById('summary-subtotal').textContent = formatNum(subtotal);
    document.getElementById('summary-tax').textContent      = formatNum(tax);
    document.getElementById('summary-total').textContent    = formatNum(total);

    if (paymentMethod === 'cash') {
        document.getElementById('tendered-row').classList.remove('hidden');
        recalcChange();
    }
}

function recalcChange() {
    const total    = parseFloat(document.getElementById('summary-total').textContent.replace(/,/g, '')) || 0;
    const tendered = parseFloat(document.getElementById('tendered-input').value) || 0;
    const change   = Math.max(0, tendered - total);
    document.getElementById('change-display').textContent = formatNum(change);
}

// ── Payment method ──────────────────────────────────────────────────────────
function selectPaymentMethod(method) {
    paymentMethod = method;
    document.querySelectorAll('.pay-method-btn').forEach(btn => {
        const active = btn.dataset.method === method;
        btn.classList.toggle('bg-blue-600', active);
        btn.classList.toggle('text-white', active);
        btn.classList.toggle('border-blue-600', active);
        btn.classList.toggle('bg-white', !active);
        btn.classList.toggle('text-gray-700', !active);
        btn.classList.toggle('border-gray-300', !active);
    });

    document.getElementById('mpesa-phone-row').classList.toggle('hidden', method !== 'mpesa');
    document.getElementById('tendered-row').classList.toggle('hidden', method !== 'cash');
}

function toggleDiscount() {
    document.getElementById('discount-row').classList.toggle('hidden');
}

// ── Session ─────────────────────────────────────────────────────────────────
function openSessionModal() {
    document.getElementById('session-modal').classList.remove('hidden');
}

async function submitOpenSession() {
    const warehouseId = document.getElementById('session-warehouse').value;
    const openingCash = parseFloat(document.getElementById('opening-cash')?.value) || 0;
    const res = await fetch(SESSION_OPEN_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ branch_id: null, warehouse_id: warehouseId, opening_cash: openingCash }),
    });
    if (res.ok) { location.reload(); } else { alert('Failed to open session.'); }
}

async function closeSession() {
    if (!confirm('Close POS session?')) return;
    await fetch(SESSION_CLOSE_URL, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    location.reload();
}

// ── Charge (F9) ─────────────────────────────────────────────────────────────
async function charge() {
    if (cart.length === 0) { alert('Cart is empty.'); return; }

    const warehouseId = document.getElementById('pos-app').dataset.warehouse;
    if (!warehouseId) { alert('Please select a warehouse first.'); return; }

    const discount = parseFloat(document.getElementById('discount-input').value) || 0;
    const total    = parseFloat(document.getElementById('summary-total').textContent.replace(/,/g, '')) || 0;
    const tendered = paymentMethod === 'cash' ? parseFloat(document.getElementById('tendered-input').value) || null : null;

    if (paymentMethod === 'cash' && tendered !== null && tendered < total) {
        alert('Amount tendered is less than total (' + formatNum(total) + ').');
        return;
    }

    const payload = {
        warehouse_id:    warehouseId,
        payment_method:  paymentMethod,
        discount:        discount,
        tax:             0,
        amount_tendered: tendered,
        mpesa_phone:     document.getElementById('mpesa-phone').value || null,
        items:           cart.map(i => ({
            product_id: i.id,
            qty:        i.qty,
            unit_price: i.unit_price,
            cost_price: i.cost_price,
            discount:   0,
        })),
    };

    const customer = document.getElementById('customer-select').value;
    if (customer) { payload.customer_id = customer; }

    document.getElementById('charge-btn').disabled = true;

    try {
        const res = await fetch(SALE_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (!res.ok) {
            alert(data.message || 'Sale failed.');
            return;
        }

        const change = data.change_given ?? 0;
        document.getElementById('complete-total').textContent  = 'Total: TZS ' + formatNum(total);
        document.getElementById('complete-change').textContent = change > 0 ? 'Change: TZS ' + formatNum(change) : '';
        document.getElementById('receipt-link').href           = '/pos/sales/' + data.sale.id + '/receipt/thermal';
        document.getElementById('complete-modal').classList.remove('hidden');

    } finally {
        document.getElementById('charge-btn').disabled = false;
    }
}

function newSale() {
    cart = [];
    document.getElementById('complete-modal').classList.add('hidden');
    document.getElementById('discount-input').value = '0';
    document.getElementById('tendered-input').value = '';
    document.getElementById('barcode-input').value  = '';
    renderCart();
    document.getElementById('barcode-input').focus();
}

function formatNum(n) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Auto-focus barcode input when page loads
document.getElementById('barcode-input').focus();

// ─────────────────────────────────────────────────────────────────────────────
// CAMERA SCANNER EXTENSION (ZXing CDN)
// ─────────────────────────────────────────────────────────────────────────────
(function () {
    const s = document.createElement('script');
    s.src   = 'https://unpkg.com/@zxing/library@latest/umd/index.min.js';
    s.async = true;
    document.head.appendChild(s);
})();

let cameraActive = false;
let zxingReader  = null;

async function toggleCameraScanner() {
    const panel = document.getElementById('camera-scanner-panel');
    const btn   = document.getElementById('camera-scan-btn');

    if (!cameraActive) {
        panel.classList.remove('hidden');
        btn.style.background = '#1d4ed8';
        btn.style.boxShadow  = '0 0 0 3px rgba(59,130,246,.35)';

        if (typeof ZXing === 'undefined') {
            showPosToast('Scanner still loading — please try again.', 'warning');
            panel.classList.add('hidden');
            btn.style.background = '#2563eb';
            btn.style.boxShadow  = '';
            return;
        }

        try {
            zxingReader       = new ZXing.BrowserMultiFormatReader();
            const devices     = await ZXing.BrowserCodeReader.listVideoInputDevices();
            const deviceId    = devices.find(d => /back|rear|environment/i.test(d.label))?.deviceId
                                ?? devices[0]?.deviceId;

            await zxingReader.decodeFromVideoDevice(deviceId, 'scanner-video', (result) => {
                if (!result) return;
                const barcode = result.getText();
                showScanFlash(barcode);
                const input = document.getElementById('barcode-input');
                input.value = barcode;
                clearTimeout(searchDebounce);
                doSearch(barcode);
            });

            cameraActive = true;
        } catch (err) {
            console.error('Camera error:', err);
            showPosToast('Camera access denied. Allow camera permission and try again.', 'error');
            panel.classList.add('hidden');
            btn.style.background = '#2563eb';
            btn.style.boxShadow  = '';
        }
    } else {
        stopCamera();
    }
}

function stopCamera() {
    if (zxingReader) { zxingReader.reset(); zxingReader = null; }
    const video = document.getElementById('scanner-video');
    if (video?.srcObject) {
        video.srcObject.getTracks().forEach(t => t.stop());
        video.srcObject = null;
    }
    document.getElementById('camera-scanner-panel').classList.add('hidden');
    const btn = document.getElementById('camera-scan-btn');
    btn.style.background = '#2563eb';
    btn.style.boxShadow  = '';
    cameraActive = false;
}

function showScanFlash(barcode) {
    const flash = document.getElementById('scan-result-flash');
    if (!flash) return;
    flash.textContent = '✓ ' + barcode;
    flash.classList.remove('hidden');
    setTimeout(() => flash.classList.add('hidden'), 1800);
}

// ── Cart count badge ──────────────────────────────────────────────────────
function updateCartCount() {
    const count = cart.reduce((s, i) => s + i.qty, 0);
    const el    = document.getElementById('cart-item-count');
    if (el) el.textContent = count + ' item' + (count !== 1 ? 's' : '') + ' in cart';
}

// Patch renderCart to update the badge after every render
const _renderCartBase = renderCart;
window.renderCart = function () {
    _renderCartBase();
    updateCartCount();
};

// ── Clear cart with confirmation ──────────────────────────────────────────
function clearCartConfirm() {
    if (cart.length === 0) return;
    if (!confirm('Clear all items from cart?')) return;
    cart = [];
    renderCart();
}

// ── Toast helper ──────────────────────────────────────────────────────────
function showPosToast(msg, type = 'info') {
    const palette = { error: '#ef4444', warning: '#f59e0b', success: '#22c55e', info: '#3b82f6' };
    const toast   = document.createElement('div');
    toast.style.cssText = `position:fixed;top:1rem;right:1rem;z-index:9999;padding:.75rem 1rem;
        background:${palette[type] ?? palette.info};color:white;border-radius:.75rem;
        font-size:.875rem;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,.2);max-width:280px;`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}
</script>

@endsection
