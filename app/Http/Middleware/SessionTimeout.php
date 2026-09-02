<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Invalidates the session after the tenant-configured idle timeout.
 * Reads idle_timeout (minutes) from tenant.config; defaults to 30.
 * Stubbed: session-based timeout is only relevant once §5.1 session
 * infrastructure (Sanctum / web guard + tenant.config) is wired in.
 */
class SessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $idleMinutes = $request->user()->tenant?->idleTimeoutMinutes() ?? 30;
            $lastActivity = $request->session()->get('_last_activity');

            if ($lastActivity && (time() - $lastActivity) > ($idleMinutes * 60)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson()) {
                    return response()->json(['message' => 'Session expired due to inactivity.'], 401);
                }

                return redirect()->route('login')->with('error', 'Session expired. Please log in again.');
            }

            $request->session()->put('_last_activity', time());
        }

        return $next($request);
    }
}
