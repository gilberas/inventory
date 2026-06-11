<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\CustomerTag;
use App\Models\Sale;
use App\Services\LoyaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    // ── GET /customers ────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = Customer::withCount('sales');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('phone', 'like', "%{$term}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('tag')) {
            $query->whereHas('tags', fn($q) => $q->where('name', $request->tag));
        }

        $customers = $query->latest()->paginate(15)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json(['data' => $customers]);
        }

        return view('customers.index', compact('customers'));
    }

    // ── GET /customers/segments ───────────────────────────────────────────────

    public function segments(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $segments = DB::table('customer_tags as t')
            ->where('t.tenant_id', $tenantId)
            ->leftJoin('customer_tag_pivot as p', 't.id', '=', 'p.tag_id')
            ->leftJoin('customers as c', function ($join) {
                $join->on('p.customer_id', '=', 'c.id')
                     ->whereNull('c.deleted_at');
            })
            ->leftJoin('sales as s', function ($join) {
                $join->on('s.customer_id', '=', 'c.id')
                     ->where('s.status', '=', Sale::STATUS_COMPLETED)
                     ->whereNull('s.deleted_at');
            })
            ->groupBy('t.id', 't.name', 't.color')
            ->select([
                't.id',
                't.name',
                't.color',
                DB::raw('COUNT(DISTINCT c.id) as customer_count'),
                DB::raw('COALESCE(SUM(s.grand_total), 0) as total_spent'),
            ])
            ->get();

        return response()->json(['data' => $segments]);
    }

    // ── POST /customers ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
            'type'         => 'nullable|in:retail,wholesale',
            'credit_limit' => 'nullable|numeric|min:0',
        ]);

        $customer = null;

        DB::transaction(function () use ($validated, &$customer) {
            $customer = Customer::create($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'created',
                'model_type' => Customer::class,
                'model_id'   => $customer->id,
                'new_values' => ['name' => $customer->name, 'type' => $customer->type],
            ]);
        });

        return response()->json(['customer' => $customer], 201);
    }

    // ── GET /customers/{customer} — profile + stats ───────────────────────────

    public function show(Customer $customer)
    {
        $customer->load('tags');

        $stats = [
            'total_purchases' => Sale::where('customer_id', $customer->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->count(),
            'total_spent'     => (float) Sale::where('customer_id', $customer->id)
                ->where('status', Sale::STATUS_COMPLETED)
                ->sum('grand_total'),
            'loyalty_points'  => $customer->loyalty_points,
            'balance'         => (float) $customer->balance,
        ];

        return response()->json(['customer' => $customer, 'stats' => $stats]);
    }

    // ── PUT /customers/{customer} ─────────────────────────────────────────────

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'phone'        => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
            'type'         => 'nullable|in:retail,wholesale',
            'credit_limit' => 'nullable|numeric|min:0',
            'is_active'    => 'nullable|boolean',
        ]);

        $old = $customer->only(['name', 'type', 'credit_limit', 'is_active']);

        DB::transaction(function () use ($validated, $customer, $old) {
            $customer->update($validated);

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => Customer::class,
                'model_id'   => $customer->id,
                'old_values' => $old,
                'new_values' => $customer->only(['name', 'type', 'credit_limit', 'is_active']),
            ]);
        });

        return response()->json(['customer' => $customer->fresh('tags')]);
    }

    // ── GET /customers/{customer}/history ─────────────────────────────────────

    public function history(Request $request, Customer $customer)
    {
        $query = Sale::where('customer_id', $customer->id)
            ->with(['items.product', 'payments'])
            ->latest();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }
        if ($request->filled('payment_status')) {
            $query->whereHas('payments', fn($q) => $q->where('status', $request->payment_status));
        }

        return response()->json(['data' => $query->paginate(15)->withQueryString()]);
    }

    // ── GET /customers/{customer}/balance ─────────────────────────────────────

    public function balance(Customer $customer)
    {
        return response()->json([
            'balance'          => (float) $customer->balance,
            'credit_limit'     => (float) $customer->credit_limit,
            'available_credit' => max(0.0, (float) $customer->credit_limit - (float) $customer->balance),
            'overdue_balance'  => $customer->overdue_balance,
            'is_overdue'       => $customer->overdue_balance > 0,
        ]);
    }

    // ── POST /customers/{customer}/tags ───────────────────────────────────────

    public function assignTags(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'tag_ids'   => 'required|array',
            'tag_ids.*' => 'integer|exists:customer_tags,id',
        ]);

        $customer->tags()->sync($validated['tag_ids']);

        return response()->json([
            'message' => 'Tags updated.',
            'tags'    => $customer->fresh()->tags,
        ]);
    }

    // ── DELETE /customers/{customer}/tags/{tag} ───────────────────────────────

    public function removeTag(Customer $customer, CustomerTag $tag)
    {
        $customer->tags()->detach($tag->id);

        return response()->json(['message' => 'Tag removed.']);
    }

    // ── POST /pos/loyalty/redeem ──────────────────────────────────────────────

    public function redeemLoyalty(Request $request, LoyaltyService $loyaltyService)
    {
        $validated = $request->validate([
            'customer_id'      => 'required|integer|exists:customers,id',
            'points_to_redeem' => 'required|integer|min:1',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        try {
            $discount = $loyaltyService->redeem($customer, (int) $validated['points_to_redeem']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'          => 'Loyalty points redeemed.',
            'discount_amount'  => $discount,
            'remaining_points' => $customer->fresh()->loyalty_points,
        ]);
    }

    // ── DELETE /customers/{customer} ─────────────────────────────────────────

    public function destroy(Customer $customer)
    {
        DB::transaction(function () use ($customer) {
            $customer->delete();

            ActivityLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'deleted',
                'model_type' => Customer::class,
                'model_id'   => $customer->id,
                'old_values' => ['name' => $customer->name],
            ]);
        });

        return response()->json(['message' => 'Customer deleted.']);
    }
}
