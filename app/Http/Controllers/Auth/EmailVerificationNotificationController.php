<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Mail\VerifyEmailOtpMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationNotificationController extends Controller
{
    /**
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp'   => ['required', 'numeric', 'digits:6'],
        ], [
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا.'
        ]);

        $email = $request->email;
        $throttleKey = 'verify-email-otp-' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'status'  => false,
                'message' => "لقد تجاوزت الحد الأقصى للمحاولات (5 محاولات). يرجى الانتظار لمدة {$seconds} ثانية قبل إعادة المحاولة."
            ], 429);
        }

        $cacheKey = 'otp_email_' . $email;
        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp) {
            return response()->json([
                'status'  => false,
                'message' => 'كود التحقق (OTP) انتهت صلاحيته، يرجى طلب كود جديد.'
            ], 422);
        }

        if ($storedOtp != $request->otp) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status'  => false,
                'message' => 'كود التحقق (OTP) غير صحيح، يرجى التأكد وإعادة المحاولة.'
            ], 422);
        }

        $user = User::where('email', $email)->first();
        
        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        }

        RateLimiter::clear($throttleKey);
        Cache::forget($cacheKey);

        $user->tokens()->delete();

        return response()->json([
            'status'  => true,
            'message' => 'تم تفعيل حسابك بنجاح، يمكنك الآن تسجيل الدخول.'
        ], 200);
    }

  
}