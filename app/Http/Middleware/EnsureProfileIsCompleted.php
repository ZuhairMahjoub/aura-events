<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileIsCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 1. التحقق من أن المستخدم مسجل كـ Provider
        // (بفضل الـ guard_name = 'api' في الموديل، سيعمل هذا السطر بدون مشاكل)
        if (! $user->hasRole('provider')) {
            return response()->json([
                'message' => 'Unauthorized. This action is only for providers.'
            ], 403);
        }

        // 2. إذا كان يحاول الوصول لرابط إكمال البيانات وهو مكملها مسبقاً
        if ($user->is_profile_completed && $request->is('api/provider/complete-profile')) {
            return response()->json([
                'message' => 'Your profile is already completed.'
            ], 400);
        }

        return $next($request);
    }
}