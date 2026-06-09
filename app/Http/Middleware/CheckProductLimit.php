<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckProductLimit
{
    private const LIMITS = [
        'starter'      => 500,
        'professional' => 5000,
        'enterprise'   => PHP_INT_MAX,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $plan  = data_get($user->tenant?->config, 'plan', 'starter');
            $limit = self::LIMITS[$plan] ?? 500;

            if ($limit !== PHP_INT_MAX) {
                $count = DB::table('products')
                    ->where('tenant_id', $user->tenant_id)
                    ->whereNull('deleted_at')
                    ->count();

                if ($count >= $limit) {
                    $message = "Product limit of {$limit} reached for your {$plan} plan. Upgrade to add more products.";

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message, 'limit' => $limit, 'current' => $count], 402);
                    }

                    return back()->withErrors(['limit' => $message]);
                }
            }
        }

        return $next($request);
    }
}
