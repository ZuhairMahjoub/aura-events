<?php

namespace App\Http\Controllers\Auth;

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
                'message' => 'كود التحقق (OTP) غير صحيح أو انتهت صلاحيته.'
            ], 422);
        }

        $user = User::where('email', $email)->first();
        
        $user->forceFill([
            'password' => Hash::make($request->password),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        Cache::forget($cacheKey);

        $user->tokens()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Password has been reset successfully.'
        ], 200);
    }
}