<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DeviceToken;
use App\Services\OtpService;
use App\Services\FirebaseNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class EmailVerificationNotificationController extends Controller
{
    protected OtpService $otpService;
    protected FirebaseNotificationService $firebaseNotificationService;

    public function __construct(
        OtpService $otpService,
        FirebaseNotificationService $firebaseNotificationService
    ) {
        $this->otpService = $otpService;
        $this->firebaseNotificationService = $firebaseNotificationService;
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email'        => ['required', 'email', 'exists:users,email'],
            'otp'          => ['required', 'numeric', 'digits:6'],
            'device_token' => ['nullable', 'string'],
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

        $isValid = $this->otpService->verifyOtp($email, $request->otp);

        if (!$isValid) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status'  => false,
                'message' => 'كود التحقق (OTP) غير صحيح أو انتهت صلاحيته، يرجى التأكد وإعادة المحاولة.'
            ], 422);
        }

        $user = User::where('email', $email)->first();
        
        if (is_null($user->email_verified_at)) {
            $user->email_verified_at = now();
            $user->save();
        }

        RateLimiter::clear($throttleKey);

        if ($request->filled('device_token')) {
            DeviceToken::updateOrCreate(
                ['user_id' => $user->id, 'device_token' => $request->input('device_token')],
                ['updated_at' => now()]
            );
        }

        $user->tokens()->where('expires_at', '<', now())->delete();

        $accessToken  = $user->createToken('access_token', ['access-api'], now()->addHours(1))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        $user->load('roles');

        $this->firebaseNotificationService->sendToUser(
            $user->id,
            'Welcome to Aura Events! 🎉',
            'Your account has been verified successfully. Welcome aboard!',
            [
                'action' => 'open_home',
                'user_id' => $user->id
            ]
        );

        return response()->json([
            'status'  => true,
            'message' => 'تم تفعيل حسابك بنجاح وتسجيل دخولك تلقائياً.',
            'data'    => [
                'user'          => $user,
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 60 * 60,
            ]
        ], 200);
    }
}