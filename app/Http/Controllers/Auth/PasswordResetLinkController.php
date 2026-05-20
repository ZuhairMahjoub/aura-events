<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Mail\ResetPasswordOtpMail;

class PasswordResetLinkController extends Controller
{
    /**
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.exists' => 'هذا البريد الإلكتروني غير مسجل لدينا في النظام.'
        ]);

        $email = $request->email;

        $otp = rand(100000, 999999);

        $cacheKey = 'password_reset_otp_' . $email;
        Cache::put($cacheKey, $otp, now()->addMinutes(10));

        Mail::to($email)->send(new ResetPasswordOtpMail($otp));

        return response()->json([
            'status' => true,
            'message' => 'تم إرسال كود التحقق (OTP) بنجاح إلى بريدك الإلكتروني.'
        ], 200);
    }
}