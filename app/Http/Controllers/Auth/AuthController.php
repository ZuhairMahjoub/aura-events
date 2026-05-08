<?php

namespace App\Http\Controllers\Auth;

/**
 * [AURA EVENTS - ARCHIVE]
 * الكود الموقف (Commented Out) بالأسفل هو الكود الافتراضي لـ Laravel Breeze.
 * تم استبداله بالكلاس الجديد بالأسفل لدعم:
 * 1. حقول قاعدة البيانات المخصصة (first_name, last_name, phone, city_id).
 * 2. استخدام ULIDs بدلاً من المعرفات الرقمية عبر الـ AuthService.
 * 3. نظام التحقق عبر OTP الواتساب بدلاً من روابط الإيميل.
 */

/*
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    public function store(Request $request): Response
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->string('password')),
        ]);

        event(new Registered($user));
        Auth::login($user);
        return response()->noContent();
    }
}
*/

// --- الكود النشط والجديد المعتمد للمشروع ---
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Events\UserRegistered;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected OtpService $otpService
    ) {}

    public function register(RegisterRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. إنشاء المستخدم (غير مفعل)
            $user = $this->authService->createUser($request->validated());

            // 2. إطلاق الحدث (الـ Listener سيتكفل بالباقي)
            event(new UserRegistered($user, 'phone'));

            return response()->json([
                'status' => 'success',
                'message' => 'تم إنشاء الحساب، يرجى إدخال كود الواتساب للتفعيل.',
                'user_id' => $user->id
            ], 201);
        });
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp'     => 'required|digits:6'
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);
        $phone = $this->authService->formatPhone($user->phone);

        if (!$this->otpService->verifyOtp($phone, $request->otp)) {
            return response()->json(['message' => 'الكود غير صحيح.'], 422);
        }

        return DB::transaction(function () use ($user) {
            $user->markPhoneAsVerified(); // تحديث phone_verified_at
            $token = $user->createToken('AuraToken')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'token'  => $token,
                'user'   => $user
            ]);
        });
    }

    public function login(Request $request)
    {
        $request->validate(['phone' => 'required', 'password' => 'required']);
        
        $result = $this->authService->login($request->only(['phone', 'password']));

        if (!$result) return response()->json(['message' => 'خطأ في البيانات'], 401);

        return response()->json(['status' => 'success', 'data' => $result]);
    }
}