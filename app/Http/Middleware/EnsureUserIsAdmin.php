<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // تحقق أن المستخدم مسجل دخول وعنده رول 'admin'
        if ($request->user() && $request->user()->hasRole('admin', 'api')) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized'], 403);
    }
}