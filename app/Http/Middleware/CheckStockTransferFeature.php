<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckStockTransferFeature
{
    // Plans that include the stock_transfers feature (Professional+)
    private const PLANS_WITH_FEATURE = ['professional', 'enterprise'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id) {
            $plan = data_get($user->tenant?->config, 'plan', 'starter');

            if (! in_array($plan, self::PLANS_WITH_FEATURE)) {
                $message = 'Branch stock transfers require a Professional or Enterprise plan.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'message'          => $message,
                        'upgrade_required' => true,
                    ], 402);
                }

                return back()->withErrors(['plan' => $message]);
            }
        }

        return $next($request);
    }
}
