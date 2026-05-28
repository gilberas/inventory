<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockTransferController extends Controller
{
    // GET /transfers
    public function index(Request $request)
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'createdBy'])
            ->withCount('items')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->from_warehouse_id, fn($q) => $q->where('from_warehouse_id', $request->from_warehouse_id))
            ->when($request->to_warehouse_id,   fn($q) => $q->where('to_warehouse_id',   $request->to_warehouse_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $warehouses = Warehouse::active()->get();
        $statuses   = StockTransfer::STATUSES;

        return view('transfers.index', compact('transfers', 'warehouses', 'statuses'));
    }

    // GET /transfers/create
    public function create()
    {
        $warehouses = Warehouse::active()->get();
        $products   = Product::active()->with('unit')->orderBy('name')->get();

        return view('transfers.create', compact('warehouses', 'products'));
    }

    // POST /transfers
    public function store(Request $request)
    {
        $request->validate([
            'from_warehouse_id'          => 'required|exists:warehouses,id',
            'to_warehouse_id'            => 'required|exists:warehouses,id|different:from_warehouse_id',
            'transfer_date'              => 'required|date',
            'notes'                      => 'nullable|string|max:500',
            'items'                      => 'required|array|min:1',
            'items.*.product_id'         => 'required|exists:products,id',
            'items.*.quantity'           => 'required|numeric|min:0.01',
        ]);

        DB::transaction(function () use ($request) {
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id'   => $request->to_warehouse_id,
                'created_by'        => auth()->id(),
                'status'            => 'PENDING',
                'transfer_date'     => $request->transfer_date,
                'notes'             => $request->notes,
            ]);

            foreach ($request->items as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity'   => $item['quantity'],
                ]);
            }
        });

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer created successfully.');
    }

    // GET /transfers/{transfer}
    public function show(StockTransfer $transfer)
    {
        $transfer->load(['fromWarehouse', 'toWarehouse', 'createdBy', 'approvedBy', 'items.product.unit']);

        return view('transfers.show', compact('transfer'));
    }

    // POST /transfers/{transfer}/approve
    public function approve(StockTransfer $transfer)
    {
        if ($transfer->status !== 'PENDING') {
            return back()->with('error', 'Only pending transfers can be approved.');
        }

        $transfer->update([
            'status'      => 'APPROVED',
            'approved_by' => auth()->id(),
        ]);

        return back()->with('success', 'Transfer approved.');
    }

    // POST /transfers/{transfer}/dispatch
    public function dispatch(StockTransfer $transfer)
    {
        if ($transfer->status !== 'APPROVED') {
            return back()->with('error', 'Only approved transfers can be dispatched.');
        }

        DB::transaction(function () use ($transfer) {
            // Deduct stock from source warehouse
            foreach ($transfer->items as $item) {
                StockBalance::removeStock(
                    $item->product_id,
                    $transfer->from_warehouse_id,
                    $item->quantity
                );
            }

            $transfer->update(['status' => 'IN_TRANSIT']);
        });

        return back()->with('success', 'Transfer dispatched. Stock deducted from source warehouse.');
    }

    // POST /transfers/{transfer}/receive
    public function receive(StockTransfer $transfer)
    {
        if ($transfer->status !== 'IN_TRANSIT') {
            return back()->with('error', 'Only in-transit transfers can be received.');
        }

        DB::transaction(function () use ($transfer) {
            // Add stock to destination warehouse
            foreach ($transfer->items as $item) {
                StockBalance::addStock(
                    $item->product_id,
                    $transfer->to_warehouse_id,
                    $item->quantity
                );
            }

            $transfer->update(['status' => 'COMPLETED']);
        });

        return back()->with('success', 'Transfer received. Stock added to destination warehouse.');
    }

    // POST /transfers/{transfer}/cancel  (bonus)
    public function cancel(StockTransfer $transfer)
    {
        if (!in_array($transfer->status, ['PENDING', 'APPROVED'])) {
            return back()->with('error', 'Only pending or approved transfers can be cancelled.');
        }

        $transfer->update(['status' => 'CANCELLED']);

        return back()->with('success', 'Transfer cancelled.');
    }

    // DELETE /transfers/{transfer}
    public function destroy(StockTransfer $transfer)
    {
        if ($transfer->status !== 'PENDING') {
            return back()->with('error', 'Only pending transfers can be deleted.');
        }

        $transfer->delete();

        return redirect()->route('transfers.index')
            ->with('success', 'Transfer deleted.');
    }
}
