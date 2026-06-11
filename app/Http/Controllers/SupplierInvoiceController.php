<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\GoodsReceivedNote;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Notifications\InvoiceDiscrepancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = SupplierInvoice::with(['supplier', 'grn'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return response()->json(['data' => $invoices]);
    }

    public function create()
    {
        $suppliers = Supplier::active()->get();
        $grns      = GoodsReceivedNote::where('status', GoodsReceivedNote::STATUS_CONFIRMED)->get();

        return response()->json(['suppliers' => $suppliers, 'grns' => $grns]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id'    => 'required|exists:suppliers,id',
            'grn_id'         => 'nullable|exists:goods_received_notes,id',
            'po_id'          => 'nullable|exists:purchase_orders,id',
            'invoice_number' => 'required|string|max:100',
            'amount'         => 'required|numeric|min:0',
            'tax_amount'     => 'nullable|numeric|min:0',
            'due_date'       => 'required|date',
        ]);

        $invoice = null;
        DB::transaction(function () use ($request, &$invoice) {
            $invoice = SupplierInvoice::create([
                'supplier_id'    => $request->supplier_id,
                'grn_id'         => $request->grn_id,
                'po_id'          => $request->po_id,
                'invoice_number' => $request->invoice_number,
                'amount'         => $request->amount,
                'tax_amount'     => $request->tax_amount ?? 0,
                'due_date'       => $request->due_date,
                'status'         => SupplierInvoice::STATUS_PENDING,
            ]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => SupplierInvoice::class,
                'model_id'   => $invoice->id,
                'new_values' => ['status' => SupplierInvoice::STATUS_PENDING],
            ]);
        });

        return redirect()->back()->with('success', "Invoice #{$invoice->invoice_number} created.");
    }

    public function show(SupplierInvoice $invoice)
    {
        $invoice->load(['supplier', 'grn.items.product', 'purchaseOrder']);
        return response()->json($invoice);
    }

    public function match(SupplierInvoice $invoice)
    {
        $invoice->load(['grn.items']);

        // Compare invoice amount vs GRN total; flag if discrepancy > 1%
        if ($invoice->grn) {
            $grnTotal     = $invoice->grn->items->sum(fn($i) => (float) $i->qty_received * (float) $i->unit_cost);
            $discrepancy  = $grnTotal > 0 ? abs((float) $invoice->amount - $grnTotal) / $grnTotal : 0;
            $hasDiscrepancy = $discrepancy > 0.01;
        } else {
            $hasDiscrepancy = false;
        }

        DB::transaction(function () use ($invoice, $hasDiscrepancy) {
            $invoice->update(['discrepancy_flag' => $hasDiscrepancy]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'matched',
                'model_type' => SupplierInvoice::class,
                'model_id'   => $invoice->id,
                'new_values' => ['discrepancy_flag' => $hasDiscrepancy],
            ]);

            if ($hasDiscrepancy) {
                // Notify users with purchase_orders.manage permission (Hard Rule §3: queued via ShouldQueue)
                $managers = User::where('tenant_id', $invoice->tenant_id)
                    ->permission('purchase_orders.manage')
                    ->get();

                foreach ($managers as $manager) {
                    $manager->notify(new InvoiceDiscrepancy($invoice));
                }
            }
        });

        $msg = $hasDiscrepancy
            ? 'Invoice matched with discrepancy. Managers notified.'
            : 'Invoice matched successfully. No discrepancy.';

        return redirect()->back()->with('success', $msg);
    }

    public function pay(SupplierInvoice $invoice)
    {
        abort_if($invoice->status === SupplierInvoice::STATUS_PAID, 422, 'Invoice is already paid.');

        DB::transaction(function () use ($invoice) {
            $invoice->update(['status' => SupplierInvoice::STATUS_PAID]);

            // Decrement supplier balance (amount owed decreases on payment)
            $invoice->supplier()->decrement('balance', $invoice->amount);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'paid',
                'model_type' => SupplierInvoice::class,
                'model_id'   => $invoice->id,
                'new_values' => ['status' => SupplierInvoice::STATUS_PAID],
            ]);
        });

        return redirect()->back()->with('success', "Invoice #{$invoice->invoice_number} marked as paid.");
    }
}
