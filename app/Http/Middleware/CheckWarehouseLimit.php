<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckWarehouseLimit
{
    private const LIMITS = [
        'starter'      => 1,
        'professional' => 3,
        'enterprise'   => PHP_INT_MAX,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $branchId = $request->input('branch_id');

            if ($branchId) {
                $plan  = data_get($user->tenant?->config, 'plan', 'starter');
                $limit = self::LIMITS[$plan] ?? 1;

                if ($limit !== PHP_INT_MAX) {
                    $count = DB::table('warehouses')
                        ->where('tenant_id', $user->tenant_id)
                        ->where('branch_id', $branchId)
                        ->whereNull('deleted_at')
                        ->count();

                    if ($count >= $limit) {
                        $message = "Your {$plan} plan allows {$limit} warehouse(s) per branch.";

                        if ($request->expectsJson()) {
                            return response()->json([
                                'message' => $message,
                                'limit'   => $limit,
                                'current' => $count,
                            ], 402);
                        }

                        return back()->withErrors(['limit' => $message]);
                    }
                }
            }
        }

        return $next($request);
    }
}
