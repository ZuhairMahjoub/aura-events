<?php

namespace App\Http\Controllers\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class NewPasswordController extends Controller
{
   
   public function verifyOtp(Request $request): JsonResponse
{
    $request->validate([
        'email' => ['required', 'email', 'exists:users,email'],
        'otp' => ['required', 'numeric', 'digits:6'],
    ], [
        'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا.'
    ]);

    $email = $request->email;
    
    $throttleKey = 'verify-otp-' . $email;

    if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
        $seconds = RateLimiter::availableIn($throttleKey);
        return response()->json([
            'status' => false,
            'message' => "لقد تجاوزت الحد الأقصى للمحاولات (5 محاولات). يرجى الانتظار لمدة {$seconds} ثانية قبل إعادة المحاولة."
        ], 429);
    }

    $cacheKey = 'password_reset_otp_' . $email;
    $storedOtp = Cache::get($cacheKey);

    if (!$storedOtp) {
        return response()->json([
            'status' => false,
            'message' => 'كود التحقق (OTP) انتهت صلاحيته، يرجى طلب كود جديد.'
        ], 422);
    }

    if ($storedOtp != $request->otp) {
        RateLimiter::hit($throttleKey, 60);

        return response()->json([
            'status' => false,
            'message' => 'كود التحقق (OTP) غير صحيح، يرجى التأكد وإعادة المحاولة.'
        ], 422);
    }

    RateLimiter::clear($throttleKey);

    return response()->json([
        'status' => true,
        'message' => 'تم التحقق من الكود بنجاح، يمكنك الآن تعيين كلمة مرور جديدة.'
    ], 200);
}

   
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'otp' => ['required', 'numeric', 'digits:6'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا.'
        ]);

        $email = $request->email;
        $cacheKey = 'password_reset_otp_' . $email;

        $storedOtp = Cache::get($cacheKey);

        if (!$storedOtp || $storedOtp != $request->otp) {
            return response()->json([
                'status' => false,
                'message' => 'الطلب غير صالح أو انتهت صلاحية الجلسة.'
            ], 422);
        }

        $user = User::where('email', $email)->first();
        
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        Cache::forget($cacheKey);

        $user->tokens()->where('expires_at', '<', now())->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم إعادة تعيين كلمة المرور بنجاح.'
        ], 200);
    }
}