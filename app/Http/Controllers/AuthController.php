<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Exception;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected OtpService  $otpService
    ) {}

    

private function buildRegisterResponse(User $user): JsonResponse
{
    $verificationToken = $user->createToken(
        'verification_token',
        ['verify-account'],
        now()->addMinutes(15)
    )->plainTextToken;

    return response()->json([
        'status'  => 'success',
        'message' => 'تم إنشاء الحساب بنجاح. يرجى تفعيله.',
        'data'    => [
            'user'                  => new UserResource($user),
            'requires_verification' => true,
            'verification_token'    => $verificationToken,
        ]
    ], 201);
}

   public function registerOrganizer(RegisterRequest $request): JsonResponse
{
    try {
        $result = $this->authService->register($request->validated(), ['organizer']);

        // حساب عالق - نعطيه token جديد
        if (is_array($result) && isset($result['pending_user'])) {
            return $this->buildRegisterResponse($result['pending_user']);
        }

        return $this->buildRegisterResponse($result);

    } catch (Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], $e->getCode() ?: 500);
    }
}

public function registerProvider(RegisterRequest $request): JsonResponse
{
    try {
        $result = $this->authService->register($request->validated(), ['provider','organizer']);

        // حساب عالق - نعطيه token جديد
        if (is_array($result) && isset($result['pending_user'])) {
            return $this->buildRegisterResponse($result['pending_user']);
        }

        return $this->buildRegisterResponse($result);

    } catch (Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], $e->getCode() ?: 500);
    }
}


    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'identity' => 'required|string',
                'password' => 'required|string',
            ]);

            $result = $this->authService->login($credentials);

            if (!$result) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'بيانات الاعتماد غير صحيحة'
                ], 401);
            }

            if (isset($result['error']) && $result['error'] === 'unverified') {
                $verifyToken = $result['user']->createToken(
                    'verification_token',
                    ['verify-account'],
                    now()->addMinutes(15)
                )->plainTextToken;

                return response()->json([
                    'status'  => 'error',
                    'message' => 'الحساب غير مفعّل. يرجى تفعيل حسابك أولاً.',
                    'data'    => [
                        'requires_verification' => true,
                        'verification_token'    => $verifyToken,
                    ]
                ], 403);
            }

            return $this->respondWithTokens($result['user'], 'تم تسجيل الدخول بنجاح');

        } catch (Exception $e) {
            Log::error('Login Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تسجيل الدخول، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|size:6',
            ]);

            $user = $request->user();

            if (!$user->phone) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'هذا الحساب لا يمتلك رقم هاتف مرتبط به.'
                ], 422);
            }

            if (!is_null($user->phone_verified_at)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'الحساب مفعّل مسبقاً.'
                ], 422);
            }

            $isValid = $this->otpService->verifyOtp($user->phone, $validated['code']);

            if (!$isValid) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'كود التحقق غير صحيح أو انتهت صلاحيته'
                ], 422);
            }

            $user->phone_verified_at = now();
            $user->save();
            $user->currentAccessToken()->delete();

            return $this->respondWithTokens($user, 'تم التحقق وتفعيل الحساب بنجاح');

        } catch (Exception $e) {
            Log::error('OTP Verification Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء التحقق، يرجى المحاولة لاحقاً.'
            ], 500);
        }
    }

    public function resendOtp(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!is_null($user->phone_verified_at)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'الحساب مفعّل بالفعل.'
                ], 422);
            }

            if (is_null($user->phone)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'رقم الهاتف غير مسجل في النظام.'
                ], 422);
            }

            $code = $this->otpService->generateForPhone($user->phone);
            $this->otpService->sendViaWhatsapp($user->phone, $code);

            return response()->json([
                'status'  => 'success',
                'message' => 'تم إعادة إرسال كود التحقق بنجاح.'
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], $e->getCode() ?: 429);
        }
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->currentAccessToken()->delete();

            return $this->respondWithTokens($user, 'تم تجديد التوكنات بنجاح');

        } catch (Exception $e) {
            Log::error('Refresh Token Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تجديد التوكن.'
            ], 500);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'تم تسجيل الخروج بنجاح'
            ]);

        } catch (Exception $e) {
            Log::error('Logout Error: ' . $e->getMessage());
            return response()->json([
                'status'  => 'error',
                'message' => 'حدث خطأ أثناء تسجيل الخروج.'
            ], 500);
        }
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallBack(): JsonResponse
    {
        try {
            if (class_exists('\Firebase\JWT\JWT')) {
                \Firebase\JWT\JWT::$leeway = 60;
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = $this->authService->findOrCreateGoogleUser(
                email:     $googleUser->getEmail(),
                firstName: $googleUser->offsetGet('given_name') ?? 'Google',
                lastName:  $googleUser->offsetGet('family_name') ?? 'User',
            );

            return $this->respondWithTokens($user, 'تم تسجيل الدخول بنجاح');

        } catch (Exception $e) {
            Log::error('Google Callback Error: ' . $e->getMessage());
            return response()->json(['error' => 'Auth Failed'], 500);
        }
    }

    public function handleGoogleMobileLogin(Request $request): JsonResponse
    {
        try {
            $idToken = $request->input('id_token');

            if (!$idToken) {
                return response()->json(['error' => 'Token is required'], 400);
            }

            $client = new \Google\Client(['client_id' => config('services.google.android_client_id')]);
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => app()->isProduction()]));

            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return response()->json(['error' => 'Invalid ID Token'], 401);
            }

            $nameParts = explode(' ', $payload['name'] ?? 'Google User', 2);

            $user = $this->authService->findOrCreateGoogleUser(
                email:     $payload['email'],
                firstName: $nameParts[0],
                lastName:  $nameParts[1] ?? ' ',
            );

            return $this->respondWithTokens($user, 'تم تسجيل الدخول بنجاح');

        } catch (Exception $e) {
            Log::error('Google Mobile Login Error: ' . $e->getMessage());
            return response()->json(['error' => 'Authentication failed'], 500);
        }
    }


    private function respondWithTokens(User $user, string $message): JsonResponse
    {
        $accessToken  = $user->createToken('access_token', ['*'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh_token', ['issue-access-token'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => [
                'user'          => new UserResource($user),
                'access_token'  => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in'    => 15 * 60,
            ]
        ], 200);
    }
}