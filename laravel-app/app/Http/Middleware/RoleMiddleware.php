<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Usage: ->middleware('role:admin,cashier')
     * Roles are compared against the roles.name column (English).
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->deny($request, 'يرجى تسجيل الدخول أولاً');
        }

        if (!empty($roles) && !in_array($user->getRoleName(), $roles)) {
            return $this->deny($request, 'ليس لديك صلاحية الوصول لهذه الصفحة');
        }

        return $next($request);
    }

    private function deny(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }
        return redirect()->route('login')->withErrors(['error' => $message]);
    }
}
