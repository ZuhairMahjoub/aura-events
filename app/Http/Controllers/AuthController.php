<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest as AuthLoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Services\OtpService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Exception;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str; 
use Illuminate\Support\Facades\Hash;
class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService,
        protected OtpService  $otpService
    ) {}

    /**
     * تسجيل منظم جديد
     */
    public function registerOrganizer(RegisterRequest $request): JsonResponse
    {
        // يستقبل كائن User دائماً (سواء كان جديداً أو عالقاً غير مفعل)
        $user = $this->authService->register($request->validated(), ['organizer']);

        $token = $this->generateVerificationToken($user);

        return UserResource::customResponse($user, $token)
            ->response()
            ->setStatusCode(201);
    }

    /**
     * تسجيل مزود خدمة جديد
     */
    public function registerProvider(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated(), ['provider', 'organizer']);

        $token = $this->generateVerificationToken($user);

        return UserResource::customResponse($user, $token)
            ->response()
            ->setStatusCode(201);
    }

    private function generateVerificationToken($user): string
    {
        return $user->createToken(
            'verification_token',
            ['verify-account'],
            now()->addMinutes(15)
        )->plainTextToken;
    }


public function login(AuthLoginRequest $request): JsonResponse
{
    try {
        $result = $this->authService->login($request->validated());
        
        $user = $result['user'];
        $isVerified = $result['is_verified'];

        if (!$isVerified) {
            $verifyToken = $user->createToken(
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

        $user->tokens()->delete(); 
        $accessToken = $user->createToken('access_token', ['*'])->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'تم تسجيل الدخول بنجاح.',
            'data'    => [
                'token' => $accessToken,
                'user'  => new UserResource($user),
            ]
        ], 200);

    } catch (AuthenticationException $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage() // ستظهر للمستخدم: "بيانات الاعتماد غير صحيحة."
        ], 401);

    // } catch (Exception $e) {
    //     Log::error('Login Runtime Structural Failure: ' . $e->getMessage());
    //     return response()->json([
    //         'status'  => 'error',
    //         'message' => 'حدث خطأ أثناء تسجيل الدخول، يرجى المحاولة لاحقاً.'
    //     ], 500);
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
    

//  public function handleGoogleMobileLogin(Request $request)
// {
//     $idToken = $request->input('id_token');

//     if (!$idToken) {
//         return response()->json(['error' => 'Token is required'], 400);
//     }

//     $webClientId = config('services.google.client_id');
//     $mobileClientId = config('services.google.android');

//     $client = new \Google\Client(['client_id' => $mobileClientId]);
    
//     $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

//     try {
//         $payload = $client->verifyIdToken($idToken);

//         if ($payload) {
            
            
//             if ($payload['aud'] !== $webClientId && $payload['aud'] !== $mobileClientId) {
//                  return response()->json(['error' => 'Token was not issued for this application'], 401);
//             }

//             $user = User::where('email', $payload['email'])->first();

//             if (!$user) {
//                 $fullName = $payload['name'] ?? 'Google User';
//                 $nameParts = explode(' ', $fullName, 2);
                
//                 $userData = [
//                     'first_name' => $nameParts[0],
//                     'last_name'  => $nameParts[1] ?? ' ',
//                     'email'      => $payload['email'],
//                     'password'   => \Illuminate\Support\Str::random(24),
//                     'phone'      => null,
//                 ];

//                 $user = $this->authService->createUser($userData);
//             }

//             $token = $user->createToken('google_token')->plainTextToken;

//             return response()->json([
//                 'status'  => 'success',
//                 'user'    => $user,
//                 'access_token' => $token,
//             ], 200);

//         } else {
//             return response()->json(['error' => 'Invalid ID Token'], 401);
//         }

//     } catch (\Exception $e) {
//         return response()->json([
//             'error'   => 'Authentication failed',
//             'message' => $e->getMessage()
//         ], 500);
//     }
// }
public function handleGoogleMobileLogin(Request $request)
{
    $idToken = $request->input('id_token');

    if (!$idToken) {
        return response()->json(['error' => 'Token is required'], 400);
    }

    $mobileClientId = "45320069047-hsglkfoe70gvltgroni6e5ggert8v72m.apps.googleusercontent.com";

    $client = new \Google\Client(['client_id' => $mobileClientId]);
    $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));

    try {
        $payload = $client->verifyIdToken($idToken);

        if ($payload) {
            $user = User::where('email', $payload['email'])->first();

            if (!$user) {
                $fullName = $payload['name'] ?? 'Google User';
                $nameParts = explode(' ', $fullName, 2);
                
                $userData = [
                    'first_name' => $nameParts[0],
                    'last_name'  => $nameParts[1] ?? ' ',
                    'email'      => $payload['email'],
                    'password'   => \Illuminate\Support\Str::random(24),
                    'phone'      => null,
                ];

                $user = $this->authService->createUser($userData);
            }

            $token = $user->createToken('google_token')->plainTextToken;

            return response()->json([
                'status'  => 'success',
                'user'    => $user,
                'access_token' => $token,
            ], 200);

        } else {
            return response()->json(['error' => 'Invalid ID Token'], 401);
        }

    } catch (\Exception $e) {
        return response()->json([
            'error'   => 'Authentication failed',
            'message' => $e->getMessage()
        ], 500);
    }
}
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallBack()
    {
        try {
            if (class_exists('\Firebase\JWT\JWT')) {
                \Firebase\JWT\JWT::$leeway = 60; 
            }

            $googleUser = Socialite::driver('google')->stateless()->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'first_name' => $googleUser->offsetGet('given_name') ?? 'Google',
                    'last_name'  => $googleUser->offsetGet('family_name') ?? 'User',
                    'password'   => Hash::make(Str::random(16)),
                    'email_verified_at' => now(),
                ]
            );

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'access_token' => $token,
                'user' => $user
            ], 200);

        } catch (Exception $e) {
            Log::error('Google Callback Error: ' . $e->getMessage());
            return response()->json(['error' => 'Auth Failed: ' . $e->getMessage()], 500);
        }
    }

}