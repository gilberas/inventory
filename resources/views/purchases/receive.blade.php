@extends('layouts.app')
@section('title', 'Receive Goods')

@section('content')
<div class="min-h-screen bg-gray-50 pb-24">

    {{-- Header --}}
    <div class="bg-white border-b px-4 py-3 flex items-center gap-3 sticky top-0 z-10">
        <a href="{{ route('purchases.show', $purchase) }}" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="flex-1">
            <h1 class="text-lg font-semibold text-gray-900">Receive Goods</h1>
            <p class="text-xs text-gray-500">{{ $purchase->po_number }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4 text-red-700 text-sm">
        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
    </div>
    @endif

    {{-- Barcode scanner input (auto-focused, highlights matching row) --}}
    <div class="mx-4 mt-4">
        <div class="bg-white rounded-xl shadow-sm p-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Scan Barcode</label>
            <div class="flex gap-2">
                <input type="text" id="barcode-scan-input"
                       placeholder="Scan barcode or type SKU..."
                       autocomplete="off" autofocus
                       class="flex-1 border-2 border-blue-400 focus:border-blue-600 rounded-xl px-4 py-3 text-base font-mono"
                       onkeydown="handleGrnScan(event)">
                <button type="button" onclick="openCamera()"
                        class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-3 rounded-xl transition flex items-center gap-2 text-sm font-medium"
                        title="Use camera (ZXing)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Camera
                </button>
            </div>
            <div id="scan-feedback" class="hidden mt-2 text-sm text-blue-600 font-medium"></div>
        </div>
    </div>

    {{-- Camera preview (ZXing) --}}
    <div id="camera-container" class="hidden mx-4 mt-3">
        <div class="bg-black rounded-xl overflow-hidden relative">
            <video id="camera-video" class="w-full" playsinline autoplay></video>
            <div class="absolute top-2 right-2">
                <button onclick="closeCamera()"
                        class="bg-black/60 text-white rounded-full w-8 h-8 flex items-center justify-center text-lg font-bold">
                    ×
                </button>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-red-500 opacity-75 scan-line"></div>
        </div>
        <p class="text-xs text-center text-gray-500 mt-2">Point camera at barcode to scan</p>
    </div>

    {{-- Items --}}
    <form id="grn-form" method="POST" action="{{ route('purchases.storeReceipt', $purchase) }}">
        @csrf

        <div class="mx-4 mt-4 space-y-2" id="items-list">
            @foreach($purchase->items as $i => $item)
            <input type="hidden" name="items[{{ $i }}][purchase_order_item_id]" value="{{ $item->id }}">
            <div id="row-{{ $item->id }}"
                 data-barcode="{{ $item->product->barcode ?? '' }}"
                 data-sku="{{ $item->product->sku ?? '' }}"
                 data-product-id="{{ $item->id }}"
                 data-product-name="{{ $item->product->name }}"
                 class="grn-row bg-white rounded-xl shadow-sm p-4 transition border-2 border-transparent
                    {{ $item->remaining_quantity <= 0 ? 'opacity-50' : '' }}">
                <div class="flex justify-between items-start mb-3">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $item->product->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            SKU: {{ $item->product->sku }}
                            @if($item->product->barcode)
                                · Barcode: {{ $item->product->barcode }}
                            @endif
                        </p>
                    </div>
                    @if($item->remaining_quantity <= 0)
                    <span class="ml-2 px-2 py-0.5 rounded-full bg-green-100 text-green-700 text-xs font-semibold">Done</span>
                    @endif
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm mb-3">
                    <div class="text-center bg-gray-50 rounded-lg py-2">
                        <p class="text-xs text-gray-400">Ordered</p>
                        <p class="font-bold text-gray-700">{{ $item->quantity_ordered }}</p>
                    </div>
                    <div class="text-center bg-green-50 rounded-lg py-2">
                        <p class="text-xs text-gray-400">Received</p>
                        <p class="font-bold text-green-700">{{ $item->quantity_received }}</p>
                    </div>
                    <div class="text-center {{ $item->remaining_quantity > 0 ? 'bg-yellow-50' : 'bg-gray-50' }} rounded-lg py-2">
                        <p class="text-xs text-gray-400">Remaining</p>
                        <p class="font-bold {{ $item->remaining_quantity > 0 ? 'text-yellow-700' : 'text-gray-400' }}">{{ $item->remaining_quantity }}</p>
                    </div>
                </div>

                <div>
                    <label class="text-xs text-gray-500 font-medium">Receive Now</label>
                    <input type="number"
                           name="items[{{ $i }}][quantity_received]"
                           id="qty-{{ $item->id }}"
                           value="{{ $item->remaining_quantity }}"
                           min="0" max="{{ $item->quantity_ordered }}" step="0.01"
                           class="w-full mt-1 border border-gray-300 rounded-xl px-4 py-3 text-center text-lg font-bold focus:ring-2 focus:ring-blue-500"
                           {{ $item->remaining_quantity <= 0 ? 'disabled' : '' }}>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mx-4 mt-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Received Date</label>
                <input type="date" name="received_date" value="{{ date('Y-m-d') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm">
            </div>
            <div class="mt-3">
                <label class="block text-sm font-semibold text-gray-700 mb-1">Notes (optional)</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-300 rounded-xl px-4 py-2 text-sm"
                          placeholder="Optional notes..."></textarea>
            </div>
        </div>

        {{-- Sticky CONFIRM footer --}}
        <div class="fixed bottom-0 left-0 right-0 bg-white border-t px-4 py-3 z-20">
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-2xl shadow-md transition text-lg">
                Confirm Receipt
            </button>
        </div>
    </form>
</div>

{{-- ZXing camera scanner --}}
<script>
let videoStream  = null;
let cameraActive = false;

// Map item rows by barcode and SKU for fast lookup
const itemRows = {};
document.querySelectorAll('.grn-row').forEach(row => {
    if (row.dataset.barcode) itemRows[row.dataset.barcode] = row;
    if (row.dataset.sku)     itemRows[row.dataset.sku]     = row;
});

function handleGrnScan(e) {
    if (e.key === 'Enter') {
        const val = e.target.value.trim();
        if (val) { highlightRow(val); e.target.value = ''; }
    }
}

function highlightRow(code) {
    // Clear previous highlights
    document.querySelectorAll('.grn-row').forEach(r => {
        r.classList.remove('border-blue-500', 'shadow-lg', 'ring-2', 'ring-blue-300');
    });

    const row = itemRows[code];
    const fb  = document.getElementById('scan-feedback');

    if (row) {
        row.classList.add('border-blue-500', 'shadow-lg', 'ring-2', 'ring-blue-300');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const qtyInput = row.querySelector('input[type=number]');
        if (qtyInput && !qtyInput.disabled) {
            qtyInput.focus();
            qtyInput.select();
        }
        fb.textContent = '✓ Found: ' + row.dataset.productName;
        fb.classList.remove('hidden', 'text-red-600');
        fb.classList.add('text-blue-600');
    } else {
        fb.textContent = '✗ No matching product for: ' + code;
        fb.classList.remove('hidden', 'text-blue-600');
        fb.classList.add('text-red-600');
    }
}

async function openCamera() {
    if (cameraActive) { closeCamera(); return; }

    document.getElementById('camera-container').classList.remove('hidden');
    cameraActive = true;

    try {
        videoStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
        const video = document.getElementById('camera-video');
        video.srcObject = videoStream;

        // Dynamic ZXing load
        if (typeof ZXing === 'undefined') {
            await loadScript('https://unpkg.com/@zxing/library@0.19.1/umd/index.min.js');
        }

        const hints    = new Map();
        const formats  = [ZXing.BarcodeFormat.EAN_13, ZXing.BarcodeFormat.CODE_128, ZXing.BarcodeFormat.QR_CODE];
        hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, formats);
        const reader   = new ZXing.BrowserMultiFormatReader(hints);

        reader.decodeFromVideoDevice(null, 'camera-video', (result, err) => {
            if (result) {
                highlightRow(result.getText());
                document.getElementById('barcode-scan-input').value = result.getText();
            }
        });

    } catch (err) {
        alert('Camera access denied or not available: ' + err.message);
        closeCamera();
    }
}

function closeCamera() {
    if (videoStream) {
        videoStream.getTracks().forEach(t => t.stop());
        videoStream = null;
    }
    cameraActive = false;
    document.getElementById('camera-container').classList.add('hidden');
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = reject;
        document.head.appendChild(s);
    });
}
</script>

<style>
@keyframes scan { 0%, 100% { top: 5%; } 50% { top: 90%; } }
.scan-line { position: absolute; left: 0; right: 0; animation: scan 2s ease-in-out infinite; }
</style>
@endsection
