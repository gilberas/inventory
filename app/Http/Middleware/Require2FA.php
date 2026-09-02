<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces TOTP 2FA for super_admin and business_owner roles.
 * Stubbed until §5.1 (pragmarx/google2fa-laravel) is implemented.
 */
class Require2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO §5.1: uncomment once 2FA infrastructure exists
        // $user = $request->user();
        // if ($user && $user->hasRole(['super_admin', 'business_owner'])) {
        //     if ($user->two_factor_secret && !$request->session()->get('2fa_verified')) {
        //         return $request->expectsJson()
        //             ? response()->json(['message' => '2FA verification required', 'redirect' => '/2fa/verify'], 403)
        //             : redirect()->route('2fa.verify');
        //     }
        //     if (!$user->two_factor_secret) {
        //         return $request->expectsJson()
        //             ? response()->json(['message' => '2FA setup required', 'redirect' => '/2fa/setup'], 403)
        //             : redirect()->route('2fa.setup');
        //     }
        // }

        return $next($request);
    }
}
