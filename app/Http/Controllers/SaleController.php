<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\InventoryService;
use App\Services\POSService;
use App\Mail\SaleReceiptMail;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SaleController extends Controller
{
    public function __construct(private InventoryService $inventoryService) {}

    // GET /pos/sales
    public function index(Request $request)
    {
        $sales = Sale::with(['customer', 'cashier'])
            ->when($request->status,   fn($q) => $q->where('status', $request->status))
            ->when($request->from,     fn($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->to,       fn($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->cashier_id, fn($q) => $q->where('cashier_id', $request->cashier_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return response()->json($sales);
    }

    // GET /pos/sales/{sale}
    public function show(Sale $sale)
    {
        $sale->load(['items.product.unit', 'customer', 'cashier', 'warehouse', 'payments', 'returns']);
        return response()->json($sale);
    }

    // POST /pos/sales/{sale}/void  (managers only — same-day)
    public function void(Request $request, Sale $sale)
    {
        abort_if($sale->status === Sale::STATUS_VOIDED, 422, 'Sale is already voided.');
        abort_if(
            !today()->isSameDay($sale->created_at->toDateString()),
            422,
            'Only same-day sales can be voided.'
        );

        DB::transaction(function () use ($sale) {
            $sale->load('items');
            foreach ($sale->items as $item) {
                $this->inventoryService->stockIn(
                    productId:  $item->product_id,
                    warehouseId: $sale->warehouse_id,
                    qty:        (float) $item->qty,
                    unitCost:   (float) $item->cost_price,
                    refType:    'return_in',
                    refId:      $sale->id,
                    userId:     auth()->id(),
                    notes:      "Void of sale #{$sale->id}",
                );
            }

            $sale->update(['status' => Sale::STATUS_VOIDED]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'voided',
                'model_type' => Sale::class,
                'model_id'   => $sale->id,
                'new_values' => ['status' => Sale::STATUS_VOIDED],
            ]);
        });

        return response()->json(['message' => 'Sale voided.']);
    }

    // GET /pos/sales/{sale}/receipt/thermal
    public function receiptThermal(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'cashier', 'payments']);
        return view('sales.receipt-thermal', compact('sale'));
    }

    // GET /pos/sales/{sale}/receipt/a4
    public function receiptA4(Sale $sale)
    {
        $sale->load(['items.product', 'customer', 'cashier', 'payments']);
        return Pdf::loadView('sales.receipt-a4', compact('sale'))
            ->download("receipt-{$sale->receipt_no}.pdf");
    }

    // GET /pos/sales/{sale}/receipt/email  (Hard Rule §3 — queued)
    public function receiptEmail(Sale $sale)
    {
        if ($sale->customer?->email) {
            Mail::to($sale->customer->email)->queue(new SaleReceiptMail($sale));
        }
        return response()->json(['message' => 'Receipt queued for delivery.']);
    }

    // POST /pos/sales/{sale}/return
    public function storeReturn(Request $request, Sale $sale)
    {
        abort_if($sale->status === Sale::STATUS_VOIDED, 422, 'Cannot return a voided sale.');

        $validated = $request->validate([
            'reason'                    => 'required|string|max:1000',
            'items'                     => 'required|array|min:1',
            'items.*.sale_item_id'      => 'required|exists:sale_items,id',
            'items.*.qty'               => 'required|numeric|min:0.001',
        ]);

        DB::transaction(function () use ($validated, $sale) {
            $totalRefund = 0.0;

            $saleReturn = SaleReturn::create([
                'sale_id'    => $sale->id,
                'created_by' => auth()->id(),
                'reason'     => $validated['reason'],
                'status'     => 'completed',
            ]);

            foreach ($validated['items'] as $item) {
                $saleItem    = SaleItem::findOrFail($item['sale_item_id']);
                $refundAmount = (float) $item['qty'] * (float) $saleItem->unit_price;
                $totalRefund += $refundAmount;

                SaleReturnItem::create([
                    'return_id'    => $saleReturn->id,
                    'sale_item_id' => $saleItem->id,
                    'product_id'   => $saleItem->product_id,
                    'qty'          => $item['qty'],
                    'unit_price'   => $saleItem->unit_price,
                    'refund_amount'=> $refundAmount,
                ]);

                $this->inventoryService->stockIn(
                    productId:  $saleItem->product_id,
                    warehouseId: $sale->warehouse_id,
                    qty:        (float) $item['qty'],
                    unitCost:   (float) $saleItem->cost_price,
                    refType:    'return_in',
                    refId:      $saleReturn->id,
                    userId:     auth()->id(),
                    notes:      "Return for sale #{$sale->id}",
                );
            }

            $saleReturn->update(['total_refund' => $totalRefund]);

            // Reduce credit customer's outstanding balance on return
            if ($sale->payment_method === 'credit' && $sale->customer_id) {
                Customer::where('id', $sale->customer_id)->decrement('balance', $totalRefund);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'sale_return',
                'model_type' => SaleReturn::class,
                'model_id'   => $saleReturn->id,
                'new_values' => ['total_refund' => $totalRefund, 'sale_id' => $sale->id],
            ]);
        });

        return response()->json(['message' => 'Return processed.'], 201);
    }
}
