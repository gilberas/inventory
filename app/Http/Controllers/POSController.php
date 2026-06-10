<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Sale;
use App\Services\POSService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class POSController extends Controller
{
    public function __construct(private POSService $posService) {}

    // GET /pos/session
    public function session(Request $request)
    {
        $user = $request->user();
        $session = PosSession::where('cashier_id', $user->id)
            ->where('status', PosSession::STATUS_ACTIVE)
            ->latest()
            ->first();

        return response()->json([
            'cashier' => $user->only(['id', 'name', 'email']),
            'session' => $session,
        ]);
    }

    // POST /pos/session/open  (CheckPOSTerminalLimit middleware on this route)
    public function openSession(Request $request)
    {
        $session = PosSession::create([
            'cashier_id' => $request->user()->id,
            'branch_id'  => $request->input('branch_id'),
            'status'     => PosSession::STATUS_ACTIVE,
        ]);

        return response()->json(['session' => $session], 201);
    }

    // POST /pos/session/close
    public function closeSession(Request $request)
    {
        $session = PosSession::where('cashier_id', $request->user()->id)
            ->where('status', PosSession::STATUS_ACTIVE)
            ->latest()
            ->first();

        if ($session) {
            $session->update(['status' => PosSession::STATUS_CLOSED, 'closed_at' => now()]);
        }

        return response()->json(['message' => 'Session closed.']);
    }

    // GET /pos/products/search?q=
    public function searchProducts(Request $request)
    {
        $q = $request->input('q', '');
        $warehouseId = $request->input('warehouse_id');

        $products = Product::with(['unit'])
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                      ->orWhere('sku', 'like', "%{$q}%")
                      ->orWhere('barcode', 'like', "%{$q}%");
            })
            ->limit(30)
            ->get()
            ->map(function ($p) use ($warehouseId) {
                $data = $p->only(['id', 'name', 'sku', 'barcode', 'selling_price', 'cost_price']);
                if ($warehouseId) {
                    $data['stock'] = \App\Models\Inventory::where('product_id', $p->id)
                        ->where('warehouse_id', $warehouseId)
                        ->value('quantity') ?? 0;
                }
                $data['unit'] = $p->unit?->name;
                return $data;
            });

        return response()->json($products);
    }

    // POST /pos/sales
    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id'               => 'required|exists:warehouses,id',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.qty'                => 'required|numeric|min:0.001',
            'items.*.unit_price'         => 'required|numeric|min:0',
            'items.*.cost_price'         => 'nullable|numeric|min:0',
            'items.*.discount'           => 'nullable|numeric|min:0',
            'payment_method'             => 'required|in:cash,mpesa,airtel,tigo,halo,credit,split',
            'customer_id'                => 'nullable|exists:customers,id',
            'discount'                   => 'nullable|numeric|min:0',
            'tax'                        => 'nullable|numeric|min:0',
            'mpesa_phone'                => 'required_if:payment_method,mpesa|nullable|string',
            'payments'                   => 'required_if:payment_method,split|array',
            'payments.*.method'          => 'required_if:payment_method,split|in:cash,mpesa,airtel,tigo,halo,credit',
            'payments.*.amount'          => 'required_if:payment_method,split|numeric|min:0.01',
            'payments.*.reference'       => 'nullable|string',
        ]);

        // Validate split total matches grand total
        if ($validated['payment_method'] === 'split') {
            $itemsTotal = collect($validated['items'])->sum(
                fn($i) => (float) $i['qty'] * (float) $i['unit_price'] - (float) ($i['discount'] ?? 0)
            );
            $grandTotal  = max(0.0, $itemsTotal - (float) ($validated['discount'] ?? 0) + (float) ($validated['tax'] ?? 0));
            $splitTotal  = collect($validated['payments'])->sum('amount');

            if (abs($splitTotal - $grandTotal) > 0.01) {
                return response()->json([
                    'message' => 'Split payment amounts must equal grand total.',
                    'expected' => $grandTotal,
                    'received' => $splitTotal,
                ], 422);
            }
        }

        try {
            $sale = $this->posService->processSale(
                items:         $validated['items'],
                paymentMethod: $validated['payment_method'],
                warehouseId:   (int) $validated['warehouse_id'],
                discount:      (float) ($validated['discount'] ?? 0),
                tax:           (float) ($validated['tax'] ?? 0),
                customerId:    isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
                splitPayments: $validated['payments'] ?? [],
                mpesaPhone:    $validated['mpesa_phone'] ?? null,
            );
        } catch (InsufficientStockException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['sale' => $sale], 201);
    }

    // POST /mpesa/callback  (public — no auth)
    public function mpesaCallback(Request $request)
    {
        $checkoutId = $request->input('Body.stkCallback.CheckoutRequestID')
            ?? $request->input('CheckoutRequestID');
        $resultCode = $request->input('Body.stkCallback.ResultCode')
            ?? $request->input('ResultCode', 0);

        if (!$checkoutId) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $payment = Payment::where('reference', $checkoutId)->first();
        if (!$payment) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        DB::transaction(function () use ($payment, $resultCode) {
            if ((int) $resultCode === 0) {
                $payment->update(['status' => Payment::STATUS_COMPLETED]);

                // Check if all payments for this sale are now complete
                $sale = $payment->payable;
                if ($sale instanceof Sale) {
                    $hasPending = $sale->payments()->where('status', Payment::STATUS_PENDING)->exists();
                    if (!$hasPending) {
                        $sale->update(['status' => Sale::STATUS_COMPLETED]);
                        event(new \App\Events\SaleCompleted($sale));
                    }
                }
            } else {
                $payment->update(['status' => Payment::STATUS_FAILED]);
            }
        });

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
