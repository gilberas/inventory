<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\PurchaseRequisition;
use App\Models\User;
use App\Notifications\RequisitionStatusChanged;
use App\Notifications\RequisitionSubmitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function index(Request $request)
    {
        $requisitions = PurchaseRequisition::with(['requestedBy'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $requisitions]);
        }

        return view('requisitions.index', compact('requisitions'));
    }

    public function create()
    {
        $products = Product::active()->with('unit')->get();
        return response()->json(['products' => $products]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'notes'                      => 'nullable|string',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.qty_requested'      => 'required|numeric|min:0.01',
            'items.*.suggested_supplier_id' => 'nullable|exists:suppliers,id',
        ]);

        DB::transaction(function () use ($request) {
            $requisition = PurchaseRequisition::create([
                'requested_by' => auth()->id(),
                'status'       => PurchaseRequisition::STATUS_DRAFT,
                'notes'        => $request->notes,
                'branch_id'    => $request->branch_id,
            ]);

            foreach ($request->items as $item) {
                $requisition->items()->create([
                    'product_id'            => $item['product_id'],
                    'qty_requested'         => $item['qty_requested'],
                    'suggested_supplier_id' => $item['suggested_supplier_id'] ?? null,
                    'notes'                 => $item['notes'] ?? null,
                ]);
            }

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_DRAFT],
            ]);
        });

        return redirect()->back()->with('success', 'Requisition created.');
    }

    public function show(PurchaseRequisition $requisition)
    {
        $requisition->load(['items.product.unit', 'items.suggestedSupplier', 'requestedBy', 'branch']);
        return view('requisitions.show', compact('requisition'));
    }

    public function resubmit(PurchaseRequisition $requisition)
    {
        abort_if($requisition->status !== PurchaseRequisition::STATUS_REVISION_REQUESTED, 422, 'Only requisitions awaiting revision can be resubmitted.');
        abort_if(auth()->id() !== $requisition->requested_by, 403);

        DB::transaction(function () use ($requisition) {
            $requisition->update(['status' => PurchaseRequisition::STATUS_PENDING]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'resubmitted',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_PENDING],
            ]);

            $managers = User::where('tenant_id', $requisition->tenant_id)
                ->permission('purchase_orders.manage')
                ->get();

            foreach ($managers as $manager) {
                $manager->notify(new RequisitionSubmitted($requisition));
            }
        });

        return redirect()->back()->with('success', 'Requisition resubmitted for approval.');
    }

    public function submit(PurchaseRequisition $requisition)
    {
        abort_if($requisition->status !== PurchaseRequisition::STATUS_DRAFT, 422, 'Only draft requisitions can be submitted.');

        DB::transaction(function () use ($requisition) {
            $requisition->update(['status' => PurchaseRequisition::STATUS_PENDING]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'submitted',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_PENDING],
            ]);

            // Notify all users with purchase_orders.manage permission (Hard Rule §3: queued)
            $managers = User::where('tenant_id', $requisition->tenant_id)
                ->permission('purchase_orders.manage')
                ->get();

            foreach ($managers as $manager) {
                $manager->notify(new RequisitionSubmitted($requisition));
            }
        });

        return redirect()->back()->with('success', 'Requisition submitted for approval.');
    }

    public function approve(PurchaseRequisition $requisition)
    {
        abort_if($requisition->status !== PurchaseRequisition::STATUS_PENDING, 422, 'Only pending requisitions can be approved.');

        DB::transaction(function () use ($requisition) {
            $requisition->update(['status' => PurchaseRequisition::STATUS_APPROVED]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'approved',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_APPROVED],
            ]);

            $requisition->requestedBy->notify(
                new RequisitionStatusChanged($requisition, PurchaseRequisition::STATUS_APPROVED)
            );
        });

        return redirect()->back()->with('success', 'Requisition approved.');
    }

    public function reject(Request $request, PurchaseRequisition $requisition)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        abort_if($requisition->status !== PurchaseRequisition::STATUS_PENDING, 422, 'Only pending requisitions can be rejected.');

        DB::transaction(function () use ($request, $requisition) {
            $requisition->update(['status' => PurchaseRequisition::STATUS_REJECTED]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'rejected',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_REJECTED, 'reason' => $request->reason],
            ]);

            $requisition->requestedBy->notify(
                new RequisitionStatusChanged($requisition, PurchaseRequisition::STATUS_REJECTED, $request->reason)
            );
        });

        return redirect()->back()->with('success', 'Requisition rejected.');
    }

    public function revise(Request $request, PurchaseRequisition $requisition)
    {
        $request->validate(['reason' => 'required|string|max:500']);
        abort_if($requisition->status !== PurchaseRequisition::STATUS_PENDING, 422, 'Only pending requisitions can be sent for revision.');

        DB::transaction(function () use ($request, $requisition) {
            $requisition->update(['status' => PurchaseRequisition::STATUS_REVISION_REQUESTED]);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'revision_requested',
                'model_type' => PurchaseRequisition::class,
                'model_id'   => $requisition->id,
                'new_values' => ['status' => PurchaseRequisition::STATUS_REVISION_REQUESTED, 'reason' => $request->reason],
            ]);

            $requisition->requestedBy->notify(
                new RequisitionStatusChanged($requisition, PurchaseRequisition::STATUS_REVISION_REQUESTED, $request->reason)
            );
        });

        return redirect()->back()->with('success', 'Revision requested.');
    }
}
