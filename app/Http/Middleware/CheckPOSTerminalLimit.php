<?php

namespace App\Http\Middleware;

use App\Models\PosSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPOSTerminalLimit
{
    private const PLAN_LIMITS = [
        'starter'      => 1,
        'professional' => 3,
        'enterprise'   => PHP_INT_MAX,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user  = $request->user();
        $plan  = data_get($user->tenant?->config, 'plan', 'starter');
        $limit = self::PLAN_LIMITS[$plan] ?? 1;

        if ($limit !== PHP_INT_MAX) {
            $active = PosSession::where('tenant_id', $user->tenant_id)
                ->where('status', PosSession::STATUS_ACTIVE)
                ->count();

            if ($active >= $limit) {
                return response()->json([
                    'message' => "POS terminal limit ({$limit}) reached for your '{$plan}' plan.",
                ], 403);
            }
        }

        return $next($request);
    }
}
