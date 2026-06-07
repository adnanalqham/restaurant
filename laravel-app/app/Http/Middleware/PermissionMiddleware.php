<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Usage: ->middleware('permission:inventory,reports')
     * Admin bypasses all checks.
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->deny($request, 'يرجى تسجيل الدخول أولاً');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        foreach ($permissions as $perm) {
            if ($user->hasPermission($perm)) {
                return $next($request);
            }
        }

        return $this->deny($request, 'ليس لديك الصلاحية الكافية للوصول');
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        return redirect()->route('admin.dashboard')->withErrors(['error' => $message]);
    }
}
