<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'غير مصرح، يرجى تسجيل الدخول.'
            ], 401);
        }

        // فحص التفعيل المزدوج
        $isPhoneVerified = !is_null($user->phone_verified_at);
        $isEmailVerified = !is_null($user->email_verified_at); // فحص مباشر للحقل لضمان الأمان

        if (!$isPhoneVerified && !$isEmailVerified) {
            return response()->json([
                'status'  => 'error',
                'message' => 'الحساب غير مفعّل. يرجى إتمام عملية التحقق أولاً.'
            ], 403);
        }

        return $next($request);
    }
}